# Security — Looks Smart Beauty Salon

This document tracks the implemented security controls against `00-PROJECT-BRIEF.md` §5 (Security
Baseline), what verified them, and what's still open. It's the Phase 4 (Security Hardening) output
deliverable — regenerate/update this file whenever a later phase adds or changes a security control.

Every "Verified by" column below points at something that was actually run, not just written and
assumed correct — either an automated test in `tests/Feature/Security/` or `tests/Unit/Services/`, or
a documented live/manual check (`curl` against a running server, `artisan tinker` against real
database rows, direct SQL reads). See `02-PROJECT-STATE.md` §3 (Phase 3/4 entries) for the full
narrative of what was checked and how.

## Implemented controls

| # | Control | Status | Where | Verified by |
|---|---|---|---|---|
| 1 | HTTPS + HSTS | 🟡 Partial | `app/Http/Middleware/SecurityHeaders.php` — HSTS sent only when `$request->secure()` | No production HTTPS environment exists yet to verify the header is actually sent there; confirmed absent over local plain HTTP (correct) |
| 2 | CSP + security headers | ✅ | `SecurityHeaders.php` — per-request nonce, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, locked-down `Permissions-Policy`; `CspReportController` + `/csp-report` | `tests/Feature/Security/XssPayloadTest.php` (nonce present, no `unsafe-inline`); live `curl` confirmed the nonce in the header matches the one in the rendered `<script>` tag |
| 3 | Rate limiting | ✅ | `AppServiceProvider::registerRateLimiters()` — `login`, `register`/`password-reset` (via `fortify-routes`), `contact`/`booking`/`review`/`api`/`admin-write` (defined, applied once those routes exist) | `tests/Feature/Security/RateLimitTest.php` (automated); originally live `curl`-verified against a running server (Phase 4 sub-step 1) |
| 4 | CSRF protection | ✅ | `Illuminate\Foundation\Http\Middleware\ValidateCsrfToken`, registered globally on `web`, excepted only for `csp-report` | `tests/Feature/Security/CsrfProtectionTest.php` (structural: middleware registered, except-list exact) + a documented live `curl` run against the real dev server (no token → 419, wrong token → 419, correct token → 302) — Laravel disables CSRF automatically whenever `APP_ENV=testing`, so the live check is what actually proves this, not the automated test alone |
| 5 | Anti-abuse on public forms | 🟡 Mechanism only | `app/Services/TurnstileService.php`, `app/Http/Traits/ProtectsPublicForms.php` (honeypot + time-trap) | Read/unit-level only — no public form exists yet to wire it into (Phase 7/9/11) |
| 6 | Authentication hardening | ✅ | Argon2id (`HASH_DRIVER=argon2id`), `Password::min(12)->uncompromised()`, login throttle, lockout notifications, mandatory TOTP 2FA for admin/super-admin/receptionist, Google OAuth with takeover-scenario defenses | Live end-to-end login/2FA-setup flow (Phase 3), real `$argon2id$` hash read from DB |
| 7 | Authorization (Policies) | ✅ | 32 Policy classes in `app/Policies/`, one per Eloquent model, auto-discovered; `Gate::before` bypass for `super-admin` | `tests/Feature/Security/AllModelsHavePoliciesTest.php` (coverage) + `IdorTest.php` (real owner-vs-non-owner checks against seeded/factory rows) + live `artisan tinker` checks |
| 8 | IDOR scoping | ✅ (policy layer) | Owner-based checks in `BookingPolicy`/`CustomerProfilePolicy`/`ReviewPolicy`/etc. | `tests/Feature/Security/IdorTest.php` — a real seeded customer can view their own booking/profile, a different real customer cannot |
| 9 | Mass-assignment protection | ✅ | Explicit `$fillable` on every model (Phase 2) + `Model::preventSilentlyDiscardingAttributes(true)` outside production (`AppServiceProvider::boot()`) | `tests/Feature/Security/MassAssignmentTest.php` — a non-fillable key thrown, a fillable key still works |
| 10 | Input normalization | ✅ | `app/Http/Requests/BaseFormRequest.php` — trims strings, converts empty string to `null` | Read; no concrete FormRequest exists yet to exercise end-to-end (real write endpoints land from Phase 6) |
| 11 | Secure file uploads | ✅ | `app/Services/SecureUploadService.php` — server-sniffed MIME allowlist, Intervention re-encode (strips EXIF/any appended payload), size caps, private disk, signed temporary URLs | `tests/Unit/Services/SecureUploadServiceTest.php` — a real `.php` file renamed to `.jpg` with a spoofed MIME is rejected; a genuine image round-trips through re-encoding (confirmed not byte-identical to the source) |
| 12 | HTML sanitization | ✅ (mechanism) | `app/Services/HtmlSanitizerService.php` wrapping mews/purifier, `default`+`blog` profiles | `tests/Unit/Services/HtmlSanitizerServiceTest.php` + `tests/Feature/Security/XssPayloadTest.php` — `<script>`/`onerror=` genuinely stripped. Wiring onto `posts.body` save/render is Phase 10's job |
| 13 | SQL injection | ✅ | Eloquent/query-builder parameter binding everywhere; no raw SQL with string-concatenated input anywhere in the app (Brief §9 convention) | `tests/Feature/Security/SqlInjectionTest.php` — a classic payload queried via Eloquent matches nothing; the same payload submitted to the real `/login` endpoint causes neither a 500 nor an auth bypass |
| 14 | Encrypted PII at rest | ✅ | `encrypted` casts on `bookings.guest_email`/`guest_phone`, `customer_profiles.notes`, `users.two_factor_secret`/`two_factor_recovery_codes` | Real ciphertext confirmed via direct SQL read (Phase 2) |
| 15 | Soft deletes on sensitive models | ✅ | `users`, `bookings`, `payments`, `sales` (+ several others for consistency) | Read the migrations |
| 16 | Audit logging | ✅ | `LogsActivity` + `app/Concerns/LogsAuditableActivity.php` on `User`/`Booking`/`Payment`/`Setting` — actor, IP, user agent, before/after diff; `User`'s logged attributes deliberately exclude `password`/2FA secrets | Live `artisan tinker` checks against real seeded rows for `Booking`/`Setting`/`User` (diff + ip/user_agent present, no `password` key ever appears). `Payment` uses the identical pattern but has no seeded row to exercise live — noted, not assumed |
| 17 | `.env` / debug-mode safety | ✅ | `app/Support/DebugModeGuard.php` — fails closed (throws) if `APP_ENV=production` and `APP_DEBUG=true` simultaneously, called from `AppServiceProvider::boot()` | `tests/Feature/Security/DebugModeGuardTest.php` — full environment/debug matrix + a real `artisan config:cache` run (exit code 0) |
| 18 | No source maps in production build | ✅ | Default Vite behavior (`build.sourcemap` never set) | `public/build` checked — 0 `.map` files (Phase 1) |
| 19 | Horizon/Telescope gating | 🟡 Partial (Horizon) / N/A (Telescope) | `HorizonServiceProvider`'s `viewHorizon` gate (interim email allowlist); Telescope isn't in the Brief's stack — deliberately not installed | Read the file; `/horizon` loads locally (Laravel's own `local`-env convention), real protection depends on Phase 5 RBAC replacing the interim gate |
| 20 | Encrypted, retained backups | 🟡 Partial | `spatie/laravel-backup` — AES-256 (`config/backup.php`), 30-day retention, daily `Schedule::command()`, mail failure alerting, S3 target (`BACKUP_DISKS`) | **Live-verified the encryption is real**: a real local dry-run zip reports `encryption_method=259` (AES-256) via `ZipArchive`, fails to decrypt without a password, succeeds with the correct one. **S3 itself never exercised — no real AWS credentials exist yet** (see Gaps below) |
| 21 | `composer audit` / `npm audit` clean | ✅ | Run manually each phase, wired into CI | No advisories / 0 vulnerabilities as of this phase |

## Known gaps (tracked, not silently skipped)

These are logged here — and in `02-PROJECT-STATE.md` §10 (Known Issues) — as deliberate, visible
tracked debt, not oversights:

- **S3 backup credentials don't exist yet.** The backup pipeline is fully built and its encryption is
  proven for real, but the S3 upload path, IAM permissions, and a restore drill are all unverified
  past the local disk. Fill in real `AWS_*` credentials, set `BACKUP_DISKS=s3`, and run a real
  `backup:run` + restore test before relying on this in staging/production.
- **Per-route/controller authorization calls (`$this->authorize(...)`) don't exist yet** — the Policy
  layer (32 classes, fully tested) is complete and ready, but `routes/admin.php` is still Phase 1
  placeholder shells with no real controller actions to authorize. A plain authenticated `customer`
  can currently reach `/admin/*` page shells (no data behind them yet) — see
  `UnauthorizedAdminAccessTest.php`'s explicit documentation of this and Known Issue #9. This closes
  out naturally as each phase from 5 onward builds real admin CRUD.
- **Excel formula-injection guard, server-side price calculation, webhook signature + idempotency**:
  not yet relevant — no Excel import, checkout flow, or payment webhook exists yet (Phase 6/7/12).
- **HTTPS/HSTS**: correctly implemented in code (conditional on `$request->secure()`) but never
  verified against a real HTTPS-terminated environment, since none exists yet.
- **Anti-abuse (Turnstile/honeypot)**: built and unit-level ready, not yet wired into any actual public
  form, since none exist yet (Phase 7/9/11 build the first ones).

## Test suite

```
tests/Feature/Security/
  AllModelsHavePoliciesTest.php    # every Eloquent model has a matching Policy
  CsrfProtectionTest.php           # CSRF middleware registered + except-list exact (+ live curl notes)
  DebugModeGuardTest.php           # APP_DEBUG=false enforced in production; config:cache succeeds
  IdorTest.php                     # real owner-vs-non-owner authorization checks
  MassAssignmentTest.php           # non-fillable attributes throw instead of silently discarding
  RateLimitTest.php                # register/password-reset rate limits actually trigger 429
  SqlInjectionTest.php             # classic SQLi payloads handled safely by Eloquent + a real endpoint
  UnauthorizedAdminAccessTest.php  # unauthenticated/un-2FA'd users correctly blocked from /admin
  XssPayloadTest.php               # CSP header present + sanitizer strips script/event-handler payloads

tests/Unit/Services/
  SecureUploadServiceTest.php      # the .php-renamed-.jpg rejection test (Brief §5 item 11)
  HtmlSanitizerServiceTest.php     # sanitizer strips script/event-handler payloads
```

Run: `php artisan test` (Pest). All security tests pass as of this phase.
