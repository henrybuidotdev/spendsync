# Plan: connect by email forwarding (option 1)

Status: **parked** (2026-11-15). Not started. Revisit when the app password becomes a trust/adoption problem.

## Why

A Gmail app password unlocks the whole mailbox (read, delete, send). With forwarding the user keeps their
password, and we only ever receive what their own Gmail filter sends us: bank emails.

## Flow

```
Bank ──► user's Gmail ──(filter: from bank → forward)──► u-<token>@in.spendsync.app
                                                              │  MX → Cloudflare Email Routing (catch-all)
                                                              ▼
                                   Email Worker: save raw to R2 + enqueue (Cloudflare Queue)
                                                              │  queue retries until the app answers
                                                              ▼
                                   POST /inbound-emails  (Bearer secret, X-Envelope-To, raw RFC 822 body)
                                                              ▼
                     checks ──► RawEmail::storeFetched() ──► ParseRawEmail ──► Transaction
```

There is no mailbox to read: the email is pushed to us when it arrives.

## Pieces

1. **Domain**: MX for `in.spendsync.app` → Cloudflare Email Routing, catch-all `*@in…` → Email Worker.
2. **Worker**: write the raw email to R2, enqueue a job, accept the email. Never call `message.setReject()`
   when our server is down (it is likely a permanent bounce → email lost). A queue consumer POSTs to the app and
   deletes the R2 copy on success.
3. **Per-user address**: random token (16+ chars) on a `MailAccount` row with a new `Forward` provider, so
   `EnsureMailConnected`, Settings status and `last_synced_at` keep working. "Regenerate address" button.
   Domain + webhook secret in `config/services.php` under `inbound`.
4. **Webhook** `POST /inbound-emails` → `InboundEmailsController::store`, no CSRF, bearer secret required (401 otherwise).
   Always 200 after auth, even when dropping, so nothing retries forever.
   1. Find the account by the **envelope** recipient (`X-Envelope-To`), never the `To` header (it still shows the user's Gmail).
   2. Gmail confirmation email (see below).
   3. Sender must be in `ParserRegistry::senders()`.
   4. DKIM must pass for the bank's domain (read the provider's `Authentication-Results`). Check each bank with a
      real forwarded email first; a bank that does not sign means the secret token is the only protection.
   5. `FetchedMessage::fromEml()` → `RawEmail::storeFetched()` → `ParseRawEmail::dispatch()` if new.
5. **Gmail confirmation**: Gmail now sends a **link** (no code) to the forwarding address, i.e. to us.
   Show it only if: from `forwarding-noreply@google.com`, DKIM pass for `google.com`, link host ends in `.google.com`.
   Save link + requesting address; the setup page (`wire:poll`) shows "Confirm forwarding from you@gmail.com [Confirm ↗]".
   The user taps it. Do not auto-click from the server.
6. **Setup page**: copy address → add it in Gmail Forwarding → tap Confirm → create filter with a generated
   `from:(sender1 OR sender2 …)` string built from `config/banks.php` → "Waiting for first bank email…" turns green.

## Known downsides

- Many steps in Gmail settings; too complicated for a normal user as-is. This is the main reason it is parked.
- No history: filters only act on new mail. Keep the app password as an optional one-time import.
- Workspace admins can block auto-forwarding → app password fallback.
- Adding a bank sender means users must update their filter; show a notice when the filter string changes.
- Gmail may show "you are forwarding your email" for a week; mention it on the setup page.

## Alternatives considered

- **Google OAuth `gmail.readonly`**: simplest and most trusted for users (one tap), but restricted scope →
  Google verification + yearly paid security assessment; unverified apps are capped at 100 users with a warning.
  Do it once there are real users.
- **Forward to a second Gmail + app password on it**: no code needed, better privacy, but even more user steps.
  Offer only as an optional "more privacy" guide.
- **Now**: keep the app password, with honest wording (drop "read-only" from terms), a "what we read" list in
  Settings (count of `raw_emails` by `from_address`), and a visible "Revoke at Google" button.
