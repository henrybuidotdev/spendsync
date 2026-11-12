# ThuChi Auto

Mobile-first spending tracker for Vietnamese bank customers. You connect Gmail with a **Google app password**. Every 5 minutes the app reads **only** emails from supported bank senders over IMAP (read-only), parses each transaction (amount, direction, counterparty, time, balance, description), and shows it in a web app (installable as a PWA). There you can categorize transactions and see monthly totals.

Stack: Laravel 13, Livewire 3 + Volt (Breeze), Tailwind, SQLite, database queue, Pest, `webklex/php-imap`.

Supported banks (VCB receipts verified on real emails; the rest synthetic, see "Known limitations"): **Vietcombank (VCB)**, **Techcombank (TCB)**, **MB Bank (MB)**.

## Setup

Requirements: PHP 8.3+ with `intl`, `mbstring`, `pdo_sqlite`; Composer; Node 20+.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install && npm run build
```

### Demo without Gmail

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
php artisan serve
```

Log in as **demo@thuchi.test / password**. The demo seeder imports every fixture in `tests/Fixtures/emails` through the real pipeline (`thuchi:import-eml`), and adds a few category rules so some transactions are already categorized. The fixtures are dated November 2026. If the current month is empty, the transactions page offers a button that jumps to the latest month with data.

### Queue worker and scheduler

Syncs and parsing run on the `database` queue. In two terminals:

```bash
php artisan queue:work      # runs SyncMailAccount / ParseRawEmail jobs
php artisan schedule:work   # every 5 min: queues a sync for each active account
```

In production, use a single cron entry `* * * * * php artisan schedule:run` plus a supervised `queue:work`.

### Connect a real Gmail

1. Turn on 2-Step Verification for the Google account.
2. Open <https://myaccount.google.com/apppasswords> and create an app password named "ThuChi".
3. Sign up in the app. You land on `/connect`. Paste the Gmail address and the 16-letter password (spaces are fine).
4. The app tests the IMAP login right away, saves the password encrypted, and queues the first sync (it looks back 30 days). Keep `queue:work` running.

IMAP details: `imap.gmail.com:993` over SSL with certificate validation. The app searches `[Gmail]/All Mail` (falling back to `INBOX`) with `FROM <bank sender> SINCE <date>` per sender. Messages are fetched with PEEK, so nothing is ever marked read, moved or deleted.

To revoke access, delete the app password in Google, or use **Settings → Disconnect**, which deletes the stored password.

## Artisan commands

| Command | What it does |
| --- | --- |
| `thuchi:sync {account?}` | Sync all active accounts (or one) now, inline. Parse jobs go to the queue. |
| `thuchi:reparse {--failed} {--bank=}` | Re-run parsing over stored raw emails. Keeps categories and notes. Use after fixing a parser. |
| `thuchi:import-eml {user} {path}` | Import `.eml` files (a file or a folder, recursive) as raw emails for a user id/email and parse them. Skips unsupported senders. |
| `thuchi:parse-stats` | Table per bank: total, parsed, failed, success %. |

## Tests

```bash
php artisan test
```

No test touches the network. `FakeMailboxClient` replaces IMAP: it serves `.eml` fixtures and can simulate auth or connection failures. `tests/Unit/BankParserFixturesTest.php` parses every `tests/Fixtures/emails/{BANK}/*.eml` and compares the result with its sibling `*.expected.json` (`null` means "bank email but not a transaction", such as an OTP or promo).

## How it works

```
Gmail ──IMAP SEARCH FROM+SINCE──▶ SyncMailAccount ──firstOrCreate(user_id, message_id)──▶ raw_emails
                                                                    │
                                                         ParseRawEmail (queued)
                                                                    │
                       ParserRegistry::findFor(from) ─▶ BankParser::parse() ─▶ transactions ─▶ CategoryMatcher
```

- `app/Mail/Inbox`: the `MailboxClient` interface, `WebklexGmailClient` (real) and `FakeMailboxClient` (tests).
- `app/Parsing`: the parser interface, DTOs, `ParserRegistry`, and `Support/{Money,Date,Text}` helpers. Bank parsers live in `Banks/`.
- `app/Jobs`: `SyncMailAccount` (unique per account, 120s timeout, 2 tries) and `ParseRawEmail`.
- `app/Categorization/CategoryMatcher.php`: counterparty rules match the whole accent-stripped name. Description rules match a substring. Counterparty rules are checked first.
- Times are stored in UTC and shown in `Asia/Ho_Chi_Minh` (`APP_TIMEZONE`, used as `config('app.display_timezone')`).

## Adding a new bank parser

1. **Config the sender.** Add an entry to `config/banks.php`:
   ```php
   'ACB' => [
       'name' => 'ACB',
       'senders' => ['mailalert@acb.com.vn'], // lowercase; only these are ever searched
       'parser' => \App\Parsing\Banks\AcbParser::class,
   ],
   ```
2. **Write the parser class** in `app/Parsing/Banks/AcbParser.php`. Extend `AbstractBankParser`, return the code from `bankCode()`, and implement `parse()`. The `field($text, 'Label|Other label')` helper reads `Label: value` lines from the normalized text (HTML is already flattened, one table row per line). Pass the raw strings to `$this->transaction(...)`, which parses money and dates, works out direction from the `+`/`-` sign or keywords (ghi có/credit/nhận vs ghi nợ/debit/trừ), and falls back to the received time. Return `null` for non-transaction emails. `$this->transaction()` throws `UnparseableEmailException` when the amount or direction is missing.
3. **Add fixtures.** Save real (redacted) emails as `tests/Fixtures/emails/ACB/01_something.eml`, plus one OTP or promo email. Next to each one, write `01_something.expected.json` with `direction`, `amount`, `balance_after`, `counterparty`, `description`, `reference` and `occurred_at` (ISO 8601 with `+07:00`), or `null`.
4. **Test.** Run `php artisan test --filter=fixture`. The data-driven test picks up the new folder automatically, and it also requires at least 3 transaction fixtures and 1 non-transaction fixture per bank.
5. **Re-parse stored emails.** Run `php artisan thuchi:reparse --bank=ACB` (or `--failed`), then check `php artisan thuchi:parse-stats`.

## Security and privacy

- `mail_accounts.app_password` uses the `encrypted` cast and is `$hidden`. The connect form clears the Livewire property before validation, and password parameters carry `#[\SensitiveParameter]`, so they are redacted from stack traces. Errors are stored as short codes (`invalid_credentials`, `connection_failed`).
- Parse failures log only `raw_email_id`, `bank_code` and a reason, never the body.
- Every query is scoped to the signed-in user. Policies exist for Transaction, Category, CategoryRule and MailAccount, and tests prove isolation.
- The connect form is rate-limited to 5 attempts per 10 minutes per user. "Sync now" is limited to once a minute.
- The original email is shown only to its owner, and only as escaped plain text.
- `/privacy` explains in Vietnamese what is read, what is stored and how to delete it. **Settings → Delete all data** removes the user and everything they own (FK cascades).

## Known limitations

- **Vietcombank receipts are built from real (redacted) emails; everything else is synthetic.** Techcombank, MB, and the Vietcombank balance-change format follow the typical Vietnamese notification style but have never seen a real email. Replace them with real samples before trusting the numbers (see `PROGRESS.md → Needs real samples`).
- Transfers between your own accounts count as spending.
- One Gmail account per user.
- For localized Gmail UIs, the All Mail folder lookup only knows the English and Vietnamese names. Otherwise it falls back to `INBOX`, which misses archived mail.
- Category rules are matched in PHP (SQL can't strip Vietnamese accents), so backfill is O(user's transactions). That's fine at MVP scale.
- The PWA caches static build assets only. There is no offline data.
- The language choice is stored in the session, not on the user.
