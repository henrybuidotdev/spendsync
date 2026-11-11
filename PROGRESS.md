# Progress

## Milestones

- [x] M1 Scaffold: Laravel, Breeze (Livewire), Pest, Tailwind, SQLite, vi locale, timezone, git init, README stub
- [x] M2 Data model: migrations, models, factories, default category seeding on register, policies + isolation tests
- [x] M3 Parsing core: MoneyParser, DateParser, TextNormalizer with thorough unit tests
- [x] M4 Bank parsers: VCB, TCB, MB plus fixtures and the data-driven fixture test
- [x] M5 Mailbox: interface, Webklex client, Fake client, `thuchi:import-eml`
- [x] M6 Pipeline: SyncMailAccount, ParseRawEmail, scheduler, `sync`/`reparse`/`parse-stats` commands, dedupe tests, needs_reconnect test
- [x] M7 Categorization: CategoryMatcher, rule creation from the UI flow, backfill, tests
- [ ] M8 UI: connect/onboarding, transactions list and detail, reports, settings, privacy page, PWA manifest
- [ ] M9 Demo data: DemoSeeder
- [ ] M10 Hardening

## Blocked

(none)

## Needs real samples

- VCB (`app/Parsing/Banks/VietcombankParser.php`) — no `samples/` dir; 4 synthetic + 1 OTP fixture.
- TCB (`app/Parsing/Banks/TechcombankParser.php`) — synthetic bilingual format; 3 + 1 promo.
- MB (`app/Parsing/Banks/MbBankParser.php`) — synthetic HTML + compact pipe format; 4 + 1 OTP.
- All sender addresses in `config/banks.php` are guesses (`// TODO verify`).
