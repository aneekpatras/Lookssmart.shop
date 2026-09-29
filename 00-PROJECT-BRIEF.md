# 00 — PROJECT BRIEF (Constitution)
**Project:** Looks Smart Beauty Salon — Booking + Salon Management Platform
**Rebuild reason:** The old version was built on Base44 (no-code). This is a full custom build.
**Owner:** (your name) · **Started:** (date)

> This file is the project's constitution. Every phase prompt references it.
> Any AI tool (Claude / Cursor / Copilot / GPT) should be able to read this file plus
> `02-PROJECT-STATE.md` and continue the project from exactly where it stands — with no re-explaining.

---

## 1. Tech Stack (LOCKED — no changes without a written decision)

| Layer | Choice |
|---|---|
| Language | PHP 8.3+ |
| Backend | Laravel 11, Eloquent ORM |
| Frontend | React 18+ + Inertia.js v2 (monolith SPA, no separate API app) |
| Build | Vite 5 |
| Styling | Tailwind CSS 3.4 + CSS variable design tokens |
| UI kit | shadcn/ui (Radix primitives) — copy-in components, no heavy library |
| Animation | Framer Motion (micro-interactions) + Lenis (smooth scroll) |
| Icons | lucide-react |
| DB | MySQL 8 (or MariaDB 10.11+) — InnoDB, utf8mb4 |
| Cache / Queue / Session / Locks | **Redis 7** |
| Queue worker | Laravel Horizon |
| Search (optional, Phase 13) | Laravel Scout + database driver; Meilisearch only if needed |
| Auth | Laravel Fortify + Socialite (Google) + 2FA (TOTP) |
| Authorization | spatie/laravel-permission (roles + permissions) |
| Media | spatie/laravel-medialibrary (conversions, WebP/AVIF) |
| Excel import/export | maatwebsite/excel |
| Activity log | spatie/laravel-activitylog |
| Backups | spatie/laravel-backup |
| Sitemap | spatie/laravel-sitemap |
| Errors | Sentry (or Flare) |
| Testing | Pest (PHP), Vitest + React Testing Library, Playwright (E2E) |
| Local env | Laravel Sail (Docker) |
| SSR | Inertia SSR enabled (required for SEO) |

### What NOT to use
- Any jQuery or Bootstrap.
- Alpine.js (Inertia + React is enough).
- Raw SQL strings where Eloquent or the Query Builder would do the job.
- `dangerouslySetInnerHTML` — only for sanitized blog HTML, and only after server-side purification.
- Any secret or API key exposed on the client.

---

## 2. Roles

| Role | Access |
|---|---|
| `super-admin` | Everything + settings + users + audit log |
| `admin` | Bookings, POS, catalog, CMS, reports (settings read-only) |
| `receptionist` | Bookings, POS, leads, messages |
| `staff` (stylist/beautician) | Own schedule + own bookings |
| `customer` | Own bookings, profile, reviews |
| `guest` | Public site + booking (guest checkout allowed) |

---

## 3. Modules (Scope)

### Public Website
`/` Home · `/about` · `/services` (+ detail) · `/deals` · `/gallery` · `/blog` (+ detail) · `/contact` · `/book` (booking flow) · `/my-account` (customer portal)

### Admin Dashboard (`/admin`)
1. Dashboard (KPIs, today's bookings, revenue snapshot)
2. Booking Management (calendar + list + status flow)
3. Booking Slots & Availability (working hours, breaks, holidays, per-staff)
4. POS (walk-in sale, cart, discounts, tax, payment, receipt)
5. Revenue & Reports (daily/weekly/monthly, per service, per staff, export)
6. Services (categories, duration, price, staff mapping, **Excel bulk import**)
7. Deals / Offers (percentage/fixed, validity, service bundles, coupon codes)
8. Pricing Management (price lists, seasonal pricing, variants)
9. Leads (from contact form / abandoned bookings, status pipeline, notes)
10. Messages (contact form inbox, reply, mark read)
11. Reviews (moderation: pending/approved/rejected, reply)
12. Blog (posts, categories, tags, SEO fields, scheduled publish)
13. Gallery (albums, images, before/after pairs)
14. **Hero Slider Manager** (WordPress-plugin style: drag-order, per-slide image/heading/CTA/animation, mobile image variant, publish schedule)
15. Customers / Users (profiles, booking history, notes, blacklist)
16. Auto Reminders (rule builder: X hours before booking → email/SMS/WhatsApp)
17. Settings (business info, timings, taxes, currency, SMTP, integrations, SEO defaults, maintenance mode)
18. Audit Log (super-admin only)

---

## 4. Booking Engine Rules (critical)
- A slot = `(staff_id, date, start_time)`, derived from service duration + buffer.
- **Double booking must be impossible:** Redis atomic lock `SET booking:lock:{staff}:{date}:{time} {uuid} NX EX 300`
  during checkout; a DB unique index on `(staff_id, start_at)` as the final guard; all wrapped in a DB transaction.
- A hold expires after 5 minutes if unpaid/unconfirmed → the lock auto-releases via TTL.
- Statuses: `pending → confirmed → checked_in → completed` / `cancelled` / `no_show`.
- Guest booking allowed (name, phone, email) — account optional.
- Confirmation email + optional SMS immediately; ICS attachment; Google Calendar sync for staff.
- Cancellation window is configurable in Settings.
- All times stored in **UTC** in the DB, displayed in the salon timezone from Settings.

---

## 5. Security Baseline (NON-NEGOTIABLE — verified in every phase)

**Transport & headers**
- HTTPS only, HSTS `max-age=31536000; includeSubDomains; preload`.
- CSP (no `unsafe-inline`; use a Vite nonce), `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: strict-origin-when-cross-origin`, minimal `Permissions-Policy`.

**Session & auth**
- Sessions in Redis; cookies `httpOnly`, `secure`, `SameSite=Lax` (admin: `Strict`).
- `session.regenerate()` on login and on privilege change; logout can optionally invalidate all device sessions.
- Passwords: Argon2id, min 12 characters, breached-password check (`Password::defaults()->uncompromised()`).
- Login throttle: 5 attempts / 15 min per IP+email, exponential backoff, lockout notification email.
- 2FA (TOTP + recovery codes) **mandatory for admin/super-admin roles**.
- Email verification required before a customer can leave a review.
- Google OAuth: state parameter verified, email must be verified by Google, account linking only on a
  verified matching email (no silent takeover).

**Input & output**
- Every request goes through a FormRequest class with strict validation rules + `prepareForValidation` normalization.
- Mass assignment: explicit `$fillable` on every model (never `$guarded = []`).
- Output escaping via React defaults; blog HTML sanitized server-side (HTMLPurifier) before storage AND before render.
- File uploads: MIME sniff + extension allowlist + max size + re-encode images through Intervention →
  strip EXIF → store outside the webroot on a private disk → serve via signed route.

**Access control**
- Laravel Policies on **every** model; controllers call `authorize()`. No `if ($user->role == ...)` scattered through the code.
- IDOR guard: always scope queries (`->where('user_id', auth()->id())` or a policy).
- Admin routes behind `auth` + `verified` + `2fa` + `role` middleware; optional IP allowlist from Settings.

**Anti-abuse**
- Rate limits: public forms 5/min, booking 10/hour/IP, API 60/min, login 5/15min.
- Honeypot field + time-trap + Cloudflare Turnstile (or reCAPTCHA v3) on contact/booking/review forms.
- CSRF on all state-changing requests (Inertia handles the token; webhooks verified separately by signature).

**Data**
- Encrypt at rest: phone, address, notes (Laravel `encrypted` cast); hash searchable columns separately if needed.
- No PII in logs. Sentry `beforeSend` scrubbing.
- Soft deletes + activity log for bookings, payments, users.
- Daily encrypted DB backup to off-site storage (S3) with 30-day retention; monthly restore test.

**Payments (if enabled)**
- Never store card data. Gateway hosted fields only. Webhook signature verification + idempotency keys.

**Supply chain**
- `composer audit` + `npm audit` in CI; Dependabot; lockfiles committed; no packages under 100 stars without review.

---

## 6. UI/UX Direction
- **Minimal luxury**: generous whitespace, max 2 fonts (a display serif for headings, e.g. Fraunces/Cormorant;
  a clean sans for body, e.g. Inter/Geist).
- Palette: soft neutral base + one warm accent (rose-gold / champagne). Dark mode optional in Phase 13.
- Glassmorphism: only on the floating nav, booking summary card, and modals — `backdrop-blur` + 1px light border.
  Never behind long text blocks.
- Motion: Framer Motion. Entrance = fade + 8px rise, 300–400ms, `easeOut`. Stagger 60ms. Hover = scale 1.02 max.
  **Respect `prefers-reduced-motion` everywhere.**
- Smooth scroll via Lenis; scroll-linked reveals via IntersectionObserver (not scroll listeners).
- Popups: booking modal, image lightbox, exit-intent offer (max once per session, cookie-controlled),
  toast notifications (sonner).
- Mobile-first. Breakpoints: 390 / 768 / 1024 / 1440. Touch targets ≥ 44px.
- Skeleton loaders, never spinners for content.

## 7. Performance Targets
- Lighthouse: Performance ≥ 95 mobile, SEO 100, Accessibility ≥ 95, Best Practices 100.
- LCP < 2.0s, INP < 200ms, CLS < 0.05.
- Images: WebP/AVIF, responsive `srcset`, `loading="lazy"` (except the LCP hero, which is
  `fetchpriority="high"` and preloaded).
- Route-based code splitting; Inertia partial reloads; `defer`/`prefetch` props.
- Redis cache for services/deals/settings with tag-based invalidation on save.

## 8. SEO Requirements
- Inertia SSR on for all public routes.
- Per page: unique `<title>` (≤60 chars), meta description (≤155 chars), canonical, OG + Twitter cards.
- JSON-LD: `BeautySalon`/`LocalBusiness` (site-wide), `Service`, `Offer`, `AggregateRating` + `Review`,
  `Article` (blog), `BreadcrumbList`, `FAQPage`.
- Auto-generated `sitemap.xml` + `robots.txt`; clean slugs; 301 map from the old Base44 URLs.
- Local SEO: NAP consistency, Google Business Profile link, embedded map, city+service landing pages.

## 9. Conventions
- Branches: `main` (prod), `develop`, `feat/<phase>-<slug>`. Conventional Commits.
- PHP: PSR-12, Laravel Pint. JS: ESLint + Prettier. TypeScript for all React files (`.tsx`).
- Structure: Actions pattern (`app/Actions/...`), FormRequests, Policies, Resources for shared props,
  Services for third-party integrations.
- Every phase ends with: tests green, Pint/ESLint clean, `02-PROJECT-STATE.md` updated,
  commit + tag `phase-N-done`.

## 10. Environment variables (running list — keep in sync)
```
APP_ENV, APP_KEY, APP_URL, APP_TIMEZONE
DB_*, REDIS_*, SESSION_DRIVER=redis, CACHE_STORE=redis, QUEUE_CONNECTION=redis
MAIL_* (Postmark/Resend/SES recommended)
GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI
GOOGLE_CALENDAR_* (service account or per-staff OAuth)
TWILIO_* / WHATSAPP_* (reminders)
TURNSTILE_SITE_KEY, TURNSTILE_SECRET
SENTRY_LARAVEL_DSN
AWS_* (S3 media + backups)
STRIPE_*/PAYMENT_* (if payments enabled)
```
