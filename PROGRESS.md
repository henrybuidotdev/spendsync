# Progress

## Milestones

- [x] M1 Scaffold: Laravel, Breeze (Livewire), Pest, Tailwind, SQLite, vi locale, timezone, git init, README stub
- [x] M2 Data model: migrations, models, factories, default category seeding on register, policies + isolation tests
- [x] M3 Parsing core: MoneyParser, DateParser, TextNormalizer with thorough unit tests
- [x] M4 Bank parsers: VCB, TCB, MB plus fixtures and the data-driven fixture test
- [x] M5 Mailbox: interface, Webklex client, Fake client, `spendsync:import-eml`
- [x] M6 Pipeline: SyncMailAccount, ParseRawEmail, scheduler, `sync`/`reparse`/`parse-stats` commands, dedupe tests, needs_reconnect test
- [x] M7 Categorization: CategoryMatcher, rule creation from the UI flow, backfill, tests
- [x] M8 UI: connect/onboarding, transactions list and detail, reports, settings, privacy page, PWA manifest
- [x] M9 Demo data: DemoSeeder
- [x] M10 Hardening

## Later

- Connect by email forwarding instead of the app password — parked, see `docs/plans/inbound-forwarding.md`.

## Blocked

(none)

## Needs real samples

- VCB (`app/Parsing/Banks/VietcombankParser.php`) — **receipts are now real** ("Biên lai chuyển tiền qua tài khoản", "Biên lai thanh toán"): 47/47 of the owner's real emails parse; 3 redacted real fixtures + 1 real non-transaction (transfer limit). Still synthetic: the "Thông báo biến động số dư" layout (fixtures 02–04) and the OTP fixture — no real ones seen yet.
- CAKE (`app/Parsing/Banks/CakeParser.php`) — built from 2 real transfer alerts (in + out), sender `no-reply@cake.vn` verified; 2 redacted real fixtures + 1 synthetic large amount + 1 synthetic OTP.
- TCB and MB parsers and fixtures were removed (they were fully synthetic); add them back from real transaction emails.
- VPB (`app/Parsing/Banks/VpBankParser.php`) — built from 1 real VPBank NEO "Transfer successful" confirmation (money out only), sender verified; 1 redacted real fixture + 2 synthetic variants + 1 synthetic OTP. No real money-in alert seen yet.
- Sender addresses: VCB `vcbdigibank@info.vietcombank.com.vn`, CAKE `no-reply@cake.vn` and VPB `vpbankonline@vpb.com.vn` are verified against real mail.

## Test status

`php artisan test`: **161 passed, 445 assertions**, 0 failing (Pest; no network). `./vendor/bin/pint --test` is clean.

## Morning report

**Done (M1–M10)**
- Laravel 13 + Breeze (Livewire/Volt) + Pest + Tailwind + SQLite, vi default / en, UTC storage with display in Asia/Ho_Chi_Minh.
- Data model with encrypted, hidden `app_password`; default Vietnamese categories seeded on user creation; owner-only policies with isolation tests.
- Parsing core: `MoneyParser` (`.`/`,`/space separators, `đ`/`VND`/`₫`, signs, decimals), `DateParser` (5+ formats), `TextNormalizer` (HTML → lines, NFC, accent folding).
- VCB / TCB / MB parsers with 14 MIME fixtures (8bit, quoted-printable, base64) and a data-driven test. The MB parser handles an HTML layout and a compact `GD:|SD:|ND:` layout.
- IMAP: `MailboxClient` interface, Webklex Gmail client (read-only PEEK, All Mail → INBOX fallback, per-sender `FROM`+`SINCE`), fake client, `spendsync:import-eml`.
- Pipeline: `SyncMailAccount` (unique, needs_reconnect on bad credentials, retry on network errors, sender allow-list), `ParseRawEmail`, per-minute scheduler (each account syncs hourly in its `id % 60` slot), `spendsync:sync`, `spendsync:reparse`, `spendsync:parse-stats` (100% on the fixtures).
- Categorization: accent-insensitive counterparty and description rules, "apply to future" checkbox (ticked by default) that remembers the choice and backfills.
- UI: connect onboarding (4 steps, trust box, banks, rate limit, synchronous login test), transactions (month switcher, Chi/Thu/Chênh lệch, day groups, filters, sync-now limited to once a minute, reconnect banner), detail (fields, category grid, note, raw text toggle), reports (CSS bars + top 5 counterparties), settings (account, disconnect, categories CRUD, rules, language, logout, delete all data), `/privacy`, PWA manifest + static-only service worker. Screenshots of every page at 360px were checked with headless Chrome.
- Demo: `DemoSeeder` → demo@spendsync.test / password, 11 transactions (6 auto-categorized).

**Synthetic, needs real samples**
- No `samples/` directory existed. All three parsers (VCB, TCB, MB) and all fixtures are synthetic. The sender addresses in `config/banks.php` are guesses (`// TODO verify`). See "Needs real samples" above.

**Blocked**
- Nothing. A live Gmail IMAP connection was never exercised: no credentials, and the rules forbid real network calls in tests.

**Top 5 next steps**
1. Collect 5–10 real redacted emails per bank (debit, credit, card, QR, OTP), drop them in `samples/`, rebuild fixtures, fix the parsers, and run `spendsync:reparse`.
2. Verify the real sender addresses for VCB/TCB/MB (some banks use several), then add the next banks by volume (ACB, BIDV, VPBank, TPBank).
3. Run one end-to-end test against a real Gmail test account (app password → first sync → parse) and measure how long an IMAP search takes per sender.
4. Track the two MVP questions: onboarding funnel events (visited /connect → submitted → success/failure reason), and a per-bank parse success dashboard built on `parse-stats` data, with alerts on new `parse_error` reasons.
5. Production hardening: a supervised queue worker, failed-job alerting, exponential backoff for `connection_failed`, auto-disable after repeated `needs_reconnect`, and `APP_KEY` rotation guidance (rotating the key makes stored app passwords unreadable).
