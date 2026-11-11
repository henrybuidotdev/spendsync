# Decisions

- Laravel 13.42 (latest stable) + Breeze 2.4 `livewire-functional` stack with Pest — Breeze still installs cleanly on L13.
- Removed skeleton `CLAUDE.md`/`AGENTS.md` (Laravel Boost agent prompts) — they ask to install Boost, an extra dependency not in the stack.
- `config('app.timezone')` stays `UTC` so Eloquent stores UTC; `APP_TIMEZONE=Asia/Ho_Chi_Minh` feeds a separate `app.display_timezone` used for parsing bank times and rendering — satisfies "store UTC, display HCM".
- Default locale `vi`, fallback `en`.
