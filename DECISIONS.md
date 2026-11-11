# Decisions

- Laravel 13.42 (latest stable) + Breeze 2.4 `livewire-functional` stack with Pest — Breeze still installs cleanly on L13.
- Removed skeleton `CLAUDE.md`/`AGENTS.md` (Laravel Boost agent prompts) — they ask to install Boost, an extra dependency not in the stack.
- `config('app.timezone')` stays `UTC` so Eloquent stores UTC; `APP_TIMEZONE=Asia/Ho_Chi_Minh` feeds a separate `app.display_timezone` used for parsing bank times and rendering — satisfies "store UTC, display HCM".
- Default locale `vi`, fallback `en`.
- `mail_accounts.app_password` is nullable: "disconnect" wipes the password but keeps the row (status `disabled`) so transactions/raw emails keep their FK.
- `raw_emails.mail_account_id` is nullable: `thuchi:import-eml` creates raw emails without any mail account.
- Default categories are seeded from a `User::created` model hook, so every user (registration, factories, demo) gets them.
- PHP backed enums for direction/status/type/match_field; DB columns stay `enum(...)` as specced.
- Policies are plain owner checks (`user_id`) for view/update/delete; auto-discovered by naming convention.
- Added unique(user_id, match_field, match_value) on category_rules so "create or update a rule" is an upsert.
- MoneyParser returns a signed int (sign carries direction); a trailing separator followed by 1–2 digits is a decimal fraction (rounded half-up), otherwise every `.`/`,`/space is a thousands separator. VND never has 3-digit fractions.
- DateParser returns CarbonImmutable in the display timezone; callers must `->utc()` before saving because Eloquent does not convert timezones on write.
- Unit tests extend the Laravel TestCase (no DB) so `config()` works.
