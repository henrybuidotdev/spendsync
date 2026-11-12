# Build: "BeanPulse" — MVP spending tracker that reads bank emails via IMAP

You are building a complete, working MVP from an empty directory. You will work **unattended overnight**. Nobody will answer questions. Read this whole document before writing code.

---

## 0. Operating rules (read first)

1. **Never stop to ask questions.** When something is ambiguous, pick the simplest reasonable option, record it in `DECISIONS.md` (one line: decision + reason), and continue.
2. **Track progress in `PROGRESS.md`.** Keep the milestone checklist from section 9 there, tick items as you finish them, and note anything blocked. If your session restarts, read `PROGRESS.md` and `DECISIONS.md` first and continue from the first unticked item.
3. **Commit after every milestone.** Use `git` with clear messages (`feat: imap fetch job`, `test: vcb parser fixtures`). Never rewrite history.
4. **Tests are the definition of done.** A milestone is done only when `php artisan test` passes. Don't delete or weaken a failing test to make it pass. Fix the code. If a test is truly wrong, fix the test and log why in `DECISIONS.md`.
5. **No real credentials, no real network calls in tests.** All IMAP access in tests goes through a fake (see section 5).
6. **Don't over-build.** Anything in "Out of scope" (section 10) must not be built, even if you finish early. If you finish early, spend the time on tests, edge cases in the parsers, and polishing the UI.
7. **Never log or print secrets.** That covers app passwords, `APP_KEY` and session data.
8. If a package install or command fails, try one alternative. If that fails too, log it in `PROGRESS.md` under "Blocked", work around it, and move on.

---

## 1. Product summary

Vietnamese users connect their Gmail with a **Google app password**. Every 5 minutes the app fetches **only** emails from supported bank senders, parses each transaction (amount, direction, counterparty/beneficiary, date-time, balance, description), and shows the transactions in a mobile-first web app where users can categorize them and see monthly totals.

The MVP has to answer two questions:
- Will users finish connecting Gmail?
- Do the parsers extract the numbers correctly?

---

## 2. Tech stack (fixed, don't substitute)

- PHP 8.3+, **latest stable Laravel**
- **Livewire 3 + Blade + Tailwind CSS**, mobile-first. Make it installable as a PWA: `manifest.webmanifest` plus a minimal service worker that only caches static assets.
- Auth: **Laravel Breeze (Livewire stack)** with email + password. Email verification is not required for the MVP.
- Database: **SQLite** for local/dev (keep migrations MySQL-compatible)
- Queue: `database` driver. Scheduler via `routes/console.php`.
- IMAP: **`webklex/laravel-imap`** (or `webklex/php-imap`), always behind our own interface
- HTML-to-text: `symfony/dom-crawler`, or `strip_tags` with careful whitespace normalization
- Tests: **Pest**
- Charts: one lightweight chart (Chart.js from npm), or pure CSS bars if that's simpler
- Timezone: `APP_TIMEZONE=Asia/Ho_Chi_Minh`. Store timestamps in UTC and display them in Asia/Ho_Chi_Minh.
- Locale: UI in **Vietnamese** by default (`lang/vi`), with English strings in `lang/en`. Keep the copy short and friendly.

---

## 3. Data model

Create migrations and models with factories:

```
users                  (Breeze default)

mail_accounts
  id, user_id FK, provider ('gmail'), email,
  app_password TEXT (Eloquent `encrypted` cast; hidden from serialization),
  status ENUM('active','needs_reconnect','disabled') default 'active',
  last_synced_at NULLABLE, last_error NULLABLE (short, no secrets),
  timestamps
  unique(user_id, email)

raw_emails
  id, user_id FK, mail_account_id FK,
  message_id STRING, from_address, subject, received_at,
  body_html LONGTEXT NULLABLE, body_text LONGTEXT NULLABLE,
  bank_code NULLABLE, parsed_at NULLABLE, parse_error TEXT NULLABLE,
  timestamps
  unique(user_id, message_id)

transactions
  id, user_id FK, raw_email_id FK NULLABLE UNIQUE,
  bank_code STRING,
  direction ENUM('in','out'),
  amount BIGINT UNSIGNED            -- VND, integer, always positive
  balance_after BIGINT NULLABLE,
  counterparty STRING NULLABLE,     -- beneficiary/sender name if present
  description TEXT NULLABLE,        -- transfer content (nội dung)
  reference STRING NULLABLE,        -- bank transaction ref if present
  occurred_at DATETIME,
  category_id FK NULLABLE,
  timestamps
  index(user_id, occurred_at)

categories
  id, user_id FK, name, icon NULLABLE (emoji), type ENUM('expense','income'), timestamps

category_rules
  id, user_id FK, match_field ENUM('counterparty','description'),
  match_value STRING (stored lowercased + accent-stripped), category_id FK, timestamps
```

When a user registers, seed default Vietnamese categories: Ăn uống 🍜, Đi lại 🛵, Mua sắm 🛍️, Hóa đơn 🧾, Nhà cửa 🏠, Giải trí 🎮, Sức khỏe 💊, Giáo dục 📚, Chuyển khoản 🔁, Khác 📦 (expense); Lương 💰, Thu khác ➕ (income).

---

## 4. Bank email parsing

### 4.1 Architecture

```
app/Parsing/
  BankEmailParser.php          interface
     public function bankCode(): string;               // 'VCB', 'TCB', 'MB', ...
     public function senders(): array;                 // lowercase sender addresses
     public function supports(string $from, string $subject): bool;
     public function parse(ParsedEmailInput $input): ?ParsedTransaction; // null = not a transaction email
  ParsedEmailInput.php         DTO: from, subject, html, text (normalized), receivedAt
  ParsedTransaction.php        DTO: direction, amount, balanceAfter, counterparty, description, reference, occurredAt
  ParserRegistry.php           holds all parsers; senders(); findFor($from,$subject)
  Support/MoneyParser.php      "1,250,000 VND", "-1.250.000 VND", "+500,000đ", "1.250.000,00" -> int
  Support/DateParser.php       "12/11/2026 14:03:21", "12-11-2026 14:03", "14:03 12/11/2026" -> Carbon (Asia/Ho_Chi_Minh)
  Support/TextNormalizer.php   html -> text, collapse whitespace, normalize unicode (NFC)
  Banks/VietcombankParser.php
  Banks/TechcombankParser.php
  Banks/MbBankParser.php
```

Parser rules:
- Amounts are integers in VND. Determine direction from the sign, or from keywords: `+`, "ghi có", "credit", "nhận" → in; `-`, "ghi nợ", "debit", "trừ" → out.
- Prefer the transaction time stated in the email body. Fall back to `received_at`.
- Return `null`, not an exception, for bank emails that aren't transactions (OTP, marketing, statements).
- Throw `UnparseableEmailException` with a short reason when an email looks like a transaction but a required field is missing. The job stores that reason in `raw_emails.parse_error`.

### 4.2 Fixtures and real samples

- Real sample emails live in `tests/Fixtures/emails/{bank_code}/*.eml`. Each has a sibling `*.expected.json` holding the expected `ParsedTransaction`.
- **If `samples/` exists at the project root**, it contains real, redacted bank emails provided by the owner. Copy them into the fixtures folders, write the `.expected.json` files by reading them carefully, and build each parser to pass them. These samples are the source of truth over anything else in this document.
- **If there are no real samples for a bank**, write 3–4 realistic synthetic fixtures per bank in that bank's typical Vietnamese notification style (e.g. "Thông báo biến động số dư", "Số tiền: -1,250,000 VND", "Số dư: ...", "Nội dung: ...", "Thời gian: ..."). Put a `// SYNTHETIC — replace with real samples` comment at the top of the parser, and list it in `PROGRESS.md` under "Needs real samples".
- A data-driven Pest test iterates every fixture: it parses each one and compares the result to its expected JSON.
- Also add one non-transaction fixture per bank (OTP/promo) that must return `null`.

### 4.3 Sender config

`config/banks.php` maps bank codes to sender addresses and display names. Put sender addresses there, not in the code. Mark guessed addresses with `// TODO verify`.

---

## 5. Mailbox access (IMAP)

```
app/Mail/Inbox/
  MailboxClient.php            interface
     connect(string $email, string $appPassword): void   // throws InvalidCredentialsException
     fetchFrom(array $senders, CarbonInterface $since): iterable<FetchedMessage>
     disconnect(): void
  FetchedMessage.php           DTO: messageId, from, subject, receivedAt, html, text
  WebklexGmailClient.php       real implementation
  FakeMailboxClient.php        test implementation, loads .eml fixtures, can simulate auth failure
```

Real client requirements:
- Host `imap.gmail.com:993`, SSL, certificate validation on.
- Search `[Gmail]/All Mail` so archived or filtered emails are found too. Fall back to `INBOX` if the folder isn't there.
- **Read-only.** Use PEEK / `leaveUnread()`. Never set flags, move or delete anything.
- Search per sender with `FROM` + `SINCE`. First sync looks back 30 days. Later syncs start from `last_synced_at - 1 day`, and duplicates are absorbed by the unique `message_id`.
- Bind the interface in a service provider. Tests bind the fake.

---

## 6. Jobs, scheduling, and the pipeline

1. `SyncMailAccount` (queued, `ShouldBeUnique` per account, timeout 120s, tries 2):
   - Connect. On `InvalidCredentialsException`, set status `needs_reconnect`, store a short `last_error`, and stop.
   - Fetch messages from supported senders. Use `firstOrCreate` on `raw_emails` (user_id, message_id). Dispatch `ParseRawEmail` for each new row.
   - Update `last_synced_at`.
2. `ParseRawEmail` (queued):
   - Find the parser through the registry. No parser: mark `parse_error = 'unsupported_sender'`.
   - `null` result: mark `parsed_at` and create no transaction.
   - Success: create the `transactions` row (unique per raw_email), then apply category rules.
   - Exception: store `parse_error` and write a `Log::warning` that includes raw_email id and bank code, but **no email body**.
3. Scheduler: every 5 minutes, dispatch `SyncMailAccount` for each `active` account. Use `withoutOverlapping()`.
4. Artisan commands:
   - `beanpulse:sync {account?}` runs a sync now (all accounts, or one).
   - `beanpulse:reparse {--failed} {--bank=}` re-runs parsing over stored raw emails. Use it after fixing a parser.
   - `beanpulse:import-eml {user} {path}` imports `.eml` files from a folder as raw emails and parses them. This is the dev and demo path that needs no Gmail.
   - `beanpulse:parse-stats` prints a table per bank: total, parsed, failed, and success %.

Categorization:
- `CategoryMatcher::match(Transaction)` checks the user's rules against the normalized counterparty, then the description. Normalized means lowercased with Vietnamese accents stripped.
- When a user sets a category in the UI, show a checkbox that is ticked by default: "Áp dụng cho các giao dịch sau với *{counterparty}*" (apply to future transactions with this counterparty). If it's ticked, create or update a rule and also apply it to that user's existing uncategorized transactions with the same counterparty.

---

## 7. UI (Livewire, mobile-first, max-width ~480px centered on desktop)

Layout: bottom tab bar with **Giao dịch** (Transactions), **Báo cáo** (Reports), **Cài đặt** (Settings).

1. **Onboarding / Connect Gmail** (`/connect`). Logged-in users with no mail account are redirected here.
   - A 4-step illustrated guide with numbered steps and plain-language copy: turn on 2-Step Verification → open `https://myaccount.google.com/apppasswords` → create an app password named "BeanPulse" → paste it here.
   - Form fields: Gmail address and the 16-character app password. Accept it with or without spaces and strip the spaces.
   - On submit, test the connection synchronously through `MailboxClient`. Show a clear Vietnamese error on failure. On success, save the account, dispatch the first sync, and redirect to Transactions with a "Đang đồng bộ…" (syncing) banner.
   - A trust box: "Chúng tôi chỉ đọc email từ ngân hàng. Mật khẩu ứng dụng được mã hóa. Bạn có thể thu hồi bất cứ lúc nào tại Google." (We only read emails from banks. The app password is encrypted. You can revoke it at any time in Google.) Link to how to revoke.
   - List the supported banks with their logos as text badges.
2. **Transactions** (`/`)
   - A month switcher (‹ Tháng 11/2026 ›) with three totals: Chi (spent), Thu (income), Chênh lệch (net).
   - A list grouped by day, each row showing the category emoji, counterparty or description (truncated), the time, and the amount (red with − for out, green with + for in). Format amounts as `1.250.000 ₫`.
   - Uncategorized rows show a small "Chưa phân loại" (uncategorized) chip.
   - Filters: all / uncategorized / in / out.
   - Pull to refresh isn't needed. A "Đồng bộ ngay" (sync now) button dispatches a sync, rate-limited to once per minute.
   - If the account is `needs_reconnect`, show a red banner linking to the reconnect form.
3. **Transaction detail** (bottom sheet or page)
   - Show all parsed fields and the bank.
   - A category picker grid with the rule checkbox described in section 6.
   - A note field (add a `note` column to transactions).
   - A "Xem email gốc" (view original email) toggle that shows the sanitized text body for debugging. Text only, never rendered HTML.
4. **Reports** (`/reports`)
   - The month switcher. One chart of spending by category for the month, plus a ranked list with amounts and percentages. Top 5 counterparties by spend.
5. **Settings** (`/settings`)
   - Connected account status, last sync time, last error. Buttons to reconnect and to disconnect. Disconnecting deletes the stored app password but keeps the transactions.
   - Category management: add, rename, delete. A deleted category's transactions become uncategorized.
   - Rule list with delete.
   - Language switch (vi/en).
   - "Xóa toàn bộ dữ liệu" (delete all data): deletes the account and all data, after confirmation.

General UI rules: Tailwind only, no component library. Large tap targets (≥44px), visible focus states, works at 360px wide. Empty states have friendly copy and a next action.

---

## 8. Security and privacy requirements

- `app_password` uses the `encrypted` cast, is in `$hidden`, and never appears in logs, exceptions, Livewire public properties after submit, or validation error bags. Clear the Livewire property right after use.
- Every query is scoped to the authenticated user. Add a Policy for Transaction, Category, CategoryRule and MailAccount, and a test proving that user A can't read or update user B's records.
- Rate-limit the connect form (5 attempts per 10 minutes per user).
- Raw email bodies are only shown to their owner, and only as escaped text.
- Never store emails from senders that aren't in `config/banks.php`. The fetch must filter at the IMAP SEARCH level, not after downloading.
- Add a `/privacy` page in Vietnamese (short and plain) explaining what is read, what is stored, and how to delete it.

---

## 9. Milestones (copy into PROGRESS.md and tick as you go)

- [ ] M1 Scaffold: Laravel, Breeze (Livewire), Pest, Tailwind, SQLite, vi locale, timezone, git init, README stub
- [ ] M2 Data model: migrations, models, factories, default category seeding on register, policies + isolation tests
- [ ] M3 Parsing core: MoneyParser, DateParser, TextNormalizer with thorough unit tests (formats with `.` and `,` separators, `đ`/`VND`/`₫` suffixes, +/− signs, Vietnamese diacritics)
- [ ] M4 Bank parsers: VCB, TCB, MB plus fixtures (real from `samples/` if present, else synthetic) and the data-driven fixture test
- [ ] M5 Mailbox: interface, Webklex client, Fake client, `beanpulse:import-eml`
- [ ] M6 Pipeline: SyncMailAccount, ParseRawEmail, scheduler, `sync`/`reparse`/`parse-stats` commands, dedupe tests, needs_reconnect test
- [ ] M7 Categorization: CategoryMatcher, rule creation from the UI flow, backfill of existing transactions, tests
- [ ] M8 UI: connect/onboarding, transactions list and detail, reports, settings, privacy page, PWA manifest
- [ ] M9 Demo data: `DemoSeeder` creates `demo@beanpulse.test` / `password` and imports all fixtures through the real pipeline via `beanpulse:import-eml`, so the app is fully explorable without Gmail
- [ ] M10 Hardening: run the full test suite, `./vendor/bin/pint`, fix any N+1 queries on the list page (eager load), check every page at 360px width by reading the Blade output carefully, finish README

---

## 10. Out of scope (do NOT build)

Gmail API/OAuth, Outlook, Google sign-in, native mobile apps, LLM/AI parsing, budgets, savings goals, debts, shared wallets, multi-currency, push notifications, payments/subscriptions, admin panel, Docker/deployment config, email-forwarding ingestion, bank-linking APIs (Casso/SePay).

---

## 11. Final deliverables (verify each before you stop)

1. `php artisan test` is all green. Report the count in `PROGRESS.md`.
2. `php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder` works. `php artisan serve` plus `npm run build` shows a populated app when you log in as the demo user.
3. `php artisan beanpulse:parse-stats` shows 100% for fixtures.
4. `README.md` covers: what it is, setup steps, how to run the queue worker and scheduler locally (`php artisan queue:work`, `php artisan schedule:work`), how to connect a real Gmail with an app password, how to add a new bank parser (step by step: config sender → parser class → fixtures → test → `beanpulse:reparse`), and known limitations.
5. `PROGRESS.md` ends with a short **Morning report**: what's done, what's synthetic and needs real samples, anything blocked, and the top 5 suggested next steps.
6. Everything committed.

Start now with M1.
