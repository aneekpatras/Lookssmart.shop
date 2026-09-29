# 01 — PHASE PROMPTS (Copy-Paste Playbook)

## How to use this
With `CLAUDE.md` in the project root, you normally never touch this file — Claude reads it itself
and picks the next phase when you type `/next`. This file is the source of truth for *what each
phase contains*, and a manual fallback if you ever want to drive a specific phase yourself or hand
the project to another tool.

**Manual mode:** attach `00-PROJECT-BRIEF.md`, `01-PHASE-PROMPTS.md`, `02-PROJECT-STATE.md`,
then paste the phase block below with this header:

> You are working on this project as a senior full-stack engineer.
> Treat the attached `00-PROJECT-BRIEF.md` (tech stack + security baseline + conventions) and
> `02-PROJECT-STATE.md` (what has been built so far) as authoritative.
> Do not skip a single point of the Brief's Security Baseline (Section 5) — verify each deliverable
> against that checklist and report on it.
> Write complete code (no `// TODO: implement later`), with full file paths. Instead of asking about
> assumptions, first give me a plan of all file paths; I'll confirm, then you write the code.
> At the end of the phase, output the **full updated version** of `02-PROJECT-STATE.md`.

One phase per chat — otherwise context fills up. Split large phases into sub-steps across chats.

---

# PHASE 0 — Foundation & Ground Rules

```
PHASE 0: Foundation.

Deliverables:
1. Repo structure plan (monorepo Laravel + Inertia React, folder-by-folder tree explaining what goes where).
2. `README.md` — setup steps, requirements, commands.
3. `.env.example` with every var from Brief Section 10, with inline comments.
4. `docker-compose` / Laravel Sail config: php 8.3, mysql 8, redis 7, mailpit, node 20.
5. Tooling config files: `pint.json`, ESLint config, `.prettierrc`, `tsconfig.json`, `.editorconfig`, `.gitignore`.
6. GitHub Actions CI: pint, eslint, tsc, pest, `composer audit`, `npm audit`, build.
7. Git strategy doc: branches, commit convention, PR checklist (including security items).
8. `02-PROJECT-STATE.md` initialized from the template.

Constraint: assume nothing has been installed or built — give me the exact commands to run.
Output order: (a) folder tree, (b) commands, (c) file contents, (d) updated PROJECT_STATE.
```

---

# PHASE 1 — Scaffold & App Shell

```
PHASE 1: Scaffold.

1. Fresh Laravel 11 install + Inertia v2 + React 18 + TypeScript + Vite + Tailwind + shadcn/ui init.
2. Inertia SSR configured and working (`php artisan inertia:start-ssr`), with production supervisor notes.
3. Redis wired for cache, session, and queue. Horizon installed and secured (only super-admin can open /horizon).
4. Two Inertia layouts: `PublicLayout` (glass nav + footer + Lenis smooth-scroll provider + Framer Motion
   page transitions) and `AdminLayout` (sidebar + topbar + breadcrumbs).
5. Design tokens: Tailwind config with our palette, typography scale, spacing, radii, shadows, and a
   `prefers-reduced-motion`-safe motion utility set. Follow Brief Section 6.
6. Reusable primitives: Button, Input, Select, Textarea, Dialog, Sheet, Card, Badge, Table,
   Toast (sonner), Skeleton, EmptyState.
7. Global error boundary + custom 403/404/419/500 Inertia error pages.
8. HandleInertiaRequests middleware: shared props = auth user (safe fields only!), flash messages,
   settings, csrf. Confirm explicitly that no sensitive field is being shared.
9. Placeholder routes for every public and admin page from Brief Section 3 — shells only.

Security checks for this phase: shared-prop leakage, Horizon route protection, Vite dev server not
exposed in production, debug mode off in production config, no source maps in the production build.
```

---

# PHASE 2 — Database Design

```
PHASE 2: Database schema.

First give me the full ERD in text (tables, columns with types, nullable, defaults, indexes, FKs,
cascade rules). I'll review it, then you write the migrations.

Tables at minimum:
users, password_reset_tokens, sessions, roles/permissions (spatie), two_factor fields,
staff (user_id, specialties, bio, photo, commission_rate, is_active),
staff_working_hours (staff_id, weekday, start, end), staff_time_off (staff_id, starts_at, ends_at, reason),
salon_holidays, business_hours,
service_categories, services (name, slug, description, duration_min, buffer_min, base_price, is_active, sort, seo fields),
service_staff (pivot), service_prices (variant/price-list based, effective_from/to),
deals (title, slug, type[percent|fixed|bundle], value, code, starts_at, ends_at, usage_limit, per_user_limit, min_amount, is_active),
deal_service (pivot), deal_redemptions,
bookings (code, customer_id nullable, guest_name/email/phone encrypted, staff_id, starts_at UTC, ends_at,
  status, source, total, discount, tax, notes, cancellation_reason, reminded_at, calendar_event_id),
booking_items (booking_id, service_id, price_snapshot, duration_snapshot),
booking_status_logs,
payments (booking_id nullable, sale_id nullable, method, amount, status, gateway_ref, idempotency_key),
sales + sale_items (POS walk-in), cash_registers/shifts,
customers (or a profile table extending users: dob, gender, preferences, notes encrypted, total_spent, visits, tags),
leads (name, phone, email, source, status, assigned_to, notes, converted_booking_id),
messages (contact form: name, email, phone, subject, body, ip, status, replied_at),
reviews (customer_id, booking_id, service_id, rating, title, body, status, admin_reply, published_at),
posts + post_categories + post_tags (blog, with SEO fields, scheduled_at),
galleries + gallery_images (album, caption, before/after pair),
sliders + slides (heading, subheading, image, mobile_image, cta_text, cta_url, animation, order,
  starts_at, ends_at, is_active),
settings (key, value json, group) — cached in Redis,
reminder_rules (event, offset_minutes, channels[], template_id, is_active),
notification_logs (channel, recipient, status, provider_ref, error),
activity_log (spatie), failed_jobs, jobs.

Requirements:
- Unique index on `(staff_id, starts_at)` in bookings — the DB-level double-booking guard.
- Indexes on every FK plus `starts_at`, `status`, `slug`, `created_at`.
- utf8mb4_unicode_ci, InnoDB, timestamps + softDeletes where the Brief calls for them.
- Money as `decimal(12,2)` — NEVER float.
- Encrypted casts for phone/address/notes.
- Then: Eloquent models with $fillable, casts, relationships, scopes; factories; and a realistic seeder
  (1 salon, 5 staff, 6 categories, 30 services, 8 deals, 200 bookings across 60 days, 40 reviews, 15 posts).
```

---

# PHASE 3 — Auth & Accounts

```
PHASE 3: Authentication.

1. Fortify: register, login, logout, email verification, password reset, password confirmation.
2. Google OAuth via Socialite — with state verification, an `email_verified` check from Google, and safe
   account-linking rules (link only if the existing account's email is verified and matches; otherwise force
   manual login + link from the profile page). Explain which takeover scenarios you defended against.
3. TOTP 2FA + recovery codes. A `require-2fa` middleware that FORCES setup for admin/super-admin/receptionist
   before they can use the dashboard.
4. spatie/laravel-permission: roles + permissions seeder exactly as in Brief Section 2. Permission-based
   gates (`bookings.view`, `pos.refund`, etc.), not role string checks.
5. Redis sessions; regenerate on login; "log out of all devices"; an active-sessions list
   (device, IP, last active) in both the customer and admin profile.
6. Login throttling + lockout email + suspicious-login (new IP/device) notification.
7. Customer portal shell: my bookings, reschedule/cancel (within the window), profile, saved preferences.
8. React auth pages designed per Brief Section 6 (minimal, glass card, motion).

Finish with a short "Auth Threat Model" table: threat → mitigation → file where it's implemented.
```

---

# PHASE 4 — Security Hardening Layer

```
PHASE 4: Security hardening. Implement Brief Section 5 line by line.

1. `SecurityHeaders` middleware: CSP with a per-request nonce wired into Vite/Inertia, HSTS,
   X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy. Plus a CSP report-only endpoint.
2. Rate limiters in `AppServiceProvider` for: login, register, password-reset, contact, booking,
   review, api, admin-write.
3. Turnstile/reCAPTCHA v3 service + honeypot + time-trap trait for public forms.
4. A base FormRequest with normalization; audit validation rules for every existing endpoint.
5. Policies for EVERY model + `Gate::before` for super-admin; a test that fails if any model lacks a policy.
6. Secure file upload pipeline: validate → sniff MIME → re-encode image → strip EXIF → private disk →
   signed temporary URLs. Non-image documents quarantined.
7. HTMLPurifier service for blog/rich-text; sanitize on save AND on render.
8. Audit logging (spatie activitylog) on bookings, payments, users, settings, roles — with actor, IP,
   user agent, and a before/after diff.
9. `.env` safety: config caching, an `APP_DEBUG=false` guard test, `/telescope` and `/horizon` gated,
   no source maps in production.
10. Encrypted daily backups (spatie/backup) to S3 + failure alerting.
11. Security test suite (Pest): CSRF, IDOR (customer A can't read B's booking), mass-assignment attempt,
    XSS payload in every text field, SQLi payload, rate-limit hit, unauthorized admin access,
    uploading a `.php` file renamed to `.jpg`.

Output at the end: SECURITY.md with the implemented-controls checklist and what's still pending.
```

---

# PHASE 5 — Admin Shell & RBAC UI

```
PHASE 5: Admin dashboard shell.

1. Final AdminLayout: collapsible sidebar (permission-filtered nav), command palette (cmd+K),
   breadcrumbs, global search, notifications bell, user menu, dark-mode toggle.
2. Reusable `DataTable` component: server-side pagination/sort/filter/search (Laravel + Inertia partial
   reloads), column visibility, bulk actions, CSV export, saved filters in the URL. This is the backbone
   of the whole admin — make it properly generic.
3. Reusable `ResourceForm` pattern: Inertia `useForm`, inline errors, unsaved-changes guard,
   optional autosave draft.
4. Dashboard home: KPI cards (today's bookings, revenue today/MTD, new leads, pending reviews),
   30-day revenue chart (Recharts), today's schedule timeline, alerts area.
5. Users & Roles admin: list, invite (signed link), assign roles, force-2FA, suspend, and impersonate
   (super-admin only, fully audit-logged, with a visible "impersonating" banner and an exit button).
6. Audit log viewer with filters.

Performance: assert query counts on every admin list route (no N+1) — verify with Laravel Debugbar/Telescope.
```

---

# PHASE 6 — Catalog: Services, Deals, Pricing, Excel Import

```
PHASE 6: Catalog module.

1. Service Categories CRUD (drag sort, image, SEO fields).
2. Services CRUD: name, slug (auto + editable), description (rich text, sanitized), duration, buffer,
   base price, category, assigned staff, images (medialibrary), is_featured, is_active, sort,
   SEO fields, FAQ repeater.
3. Pricing Management: price lists (Standard / Weekend / Seasonal), per-service overrides with
   effective_from/to, a bulk price update tool (+10% to a category), price history log.
4. Deals CRUD: percent/fixed/bundle, coupon code (unique, case-insensitive), validity window,
   usage limits (global + per user), applicable services/categories, stackable flag, auto-apply flag,
   live preview of the final price.
5. **Excel import** (maatwebsite/excel):
   - Downloadable `.xlsx` template with headers, an example row, an "Instructions" sheet, and
     data-validation dropdowns for category.
   - Upload → queued job → row-by-row validation → dry-run preview table (create / update / error per row)
     → user confirms → commit in a transaction.
   - Match on `slug` or `sku` for updates; never blind-overwrite.
   - Downloadable error report as xlsx with a reason column.
   - SECURITY: file size cap, MIME check, max rows, formula-injection guard (sanitize cells starting with
     `= + - @`), memory-safe chunked reading; the import runs with the importer's permissions and is audit-logged.
   - The same importer pattern should be reusable for deals and customers later.
6. Redis caching for public service/deal queries with tag invalidation on save.
```

---

# PHASE 7 — Booking Engine (core)

```
PHASE 7: Booking engine. Brief Section 4 is authoritative.

1. Availability engine service class:
   input (service_ids, staff_id|any, date, timezone) → output available slots.
   Logic: business hours ∩ staff working hours − time off − holidays − existing bookings − buffers −
   Redis-held slots − min lead time − max advance days. Slot granularity from Settings (default 15 min).
   Multi-service booking = summed duration, contiguous slot required.
2. Redis slot holding: `SET booking:hold:{staff}:{startISO} {holdToken} NX EX 300`. Hold at step 2 of the
   flow, extend on activity, release on abandon/complete. Race-condition test with 50 concurrent requests →
   exactly one must succeed.
3. `CreateBookingAction`: DB transaction + `lockForUpdate` + unique-index catch → a friendly
   "slot just taken" error with alternative slots suggested.
4. Booking code generator (e.g. LS-8F3K2Q), collision-safe.
5. Price calculation server-side ONLY (never trust client totals): items + deal/coupon validation +
   tax from Settings + rounding rules. Return a signed price quote.
6. Guest and logged-in bookings; auto-create a customer record on guest booking
   (matched by phone/email, with merge logic).
7. Reschedule and cancel flows with policy windows, reason capture, and slot release.
8. Booking confirmation: email (branded, with .ics attachment), optional SMS/WhatsApp, admin notification.
   Queued, retryable, logged in notification_logs.
9. Statuses and transitions guarded by a state machine (invalid transitions throw).
10. Pest tests: availability edge cases (DST change, midnight crossover, back-to-back, buffer overlap,
    staff off), concurrency, double-booking impossible, coupon abuse (reuse beyond limit),
    price tampering rejected.
```

---

# PHASE 8 — Notifications, Auto Reminders, Calendar Sync

```
PHASE 8: Notifications + reminders + calendar.

1. Notification channels: Mail (Resend/Postmark), SMS/WhatsApp (Twilio behind an abstraction so the
   provider is swappable), Database (in-app).
2. Branded email templates (responsive, dark-mode safe): booking confirmed, reminder, rescheduled,
   cancelled, review request, birthday offer, password reset, admin new-booking alert, staff daily schedule.
3. **Auto Reminder rule builder** in admin: event (before booking / after booking / birthday /
   no-show follow-up / review request), offset (e.g. 24h before, 2h before), channels, template,
   active toggle, target filters.
4. Scheduler + queued jobs: `SendDueRemindersJob` every 5 minutes, idempotent
   (`reminded_at`/notification_logs prevent duplicates), respecting quiet hours from Settings and
   customer opt-out.
5. Horizon config: separate queues (`notifications`, `imports`, `default`), retries with backoff,
   failed-job alerting to admin email/Sentry.
6. **Google Calendar sync**: per-staff OAuth connect (tokens encrypted at rest, refresh handled),
   create/update/delete events on booking changes, store `calendar_event_id`, optional two-way
   (busy blocks pulled in as unavailability), graceful degradation if a token is revoked + admin alert.
   Also a generic `.ics` download for customers.
7. Notification log viewer in admin + a resend button.
8. Unsubscribe/opt-out links (signed URLs), and a compliance note (consent captured at booking).
```

---

# PHASE 9 — Public Website (the money pages)

```
PHASE 9: Public website. Follow Brief Sections 6, 7, and 8 strictly. SSR on.

Pages:
1. HOME — hero slider (from the Slider Manager, Ken Burns/parallax, mobile image variant, LCP-optimized
   preload), value props, featured services grid, deals strip with countdown, gallery teaser masonry,
   testimonials carousel (real reviews), staff/team section, sticky "Book Now" CTA, FAQ accordion,
   location map + hours, newsletter.
2. ABOUT — story timeline with scroll reveal, team cards with hover reveal, awards/stats counters,
   salon interior gallery.
3. SERVICES — category filter (client-side, URL-synced), search, service cards with price + duration +
   "Book" CTA, and a service detail page (gallery, description, what's included, duration, price,
   related services, FAQ, book CTA, JSON-LD Service).
4. DEALS — active offers grid with countdown timers, coupon copy-to-clipboard, expired section hidden,
   "claim → prefilled booking".
5. GALLERY — albums, masonry grid, lightbox with keyboard nav + swipe, before/after slider component,
   lazy loading with blur-up placeholders.
6. BLOG — list + detail, reading time, TOC, share buttons, related posts, Article JSON-LD.
7. CONTACT — form (validated + honeypot + Turnstile + rate limited), map, hours, WhatsApp/call buttons,
   directions link.
8. BOOKING FLOW — multi-step: services → staff (or "any") → date/time (live availability calendar,
   timezone-correct) → details → review & confirm → success page with code + ICS + "add to calendar".
   Progress indicator, back-safe, state persisted, mobile-perfect, visible slot-hold countdown.

UI/UX must-haves: Lenis smooth scroll, Framer Motion reveals (staggered, reduced-motion respected),
glass floating nav that condenses on scroll, magnetic/hover micro-interactions on CTAs, skeleton loading,
toast feedback, exit-intent offer popup (once per session), floating WhatsApp button.

Every page needs: meta tags, canonical, OG image, JSON-LD, semantic headings (single h1), alt text,
and a Lighthouse-ready image strategy.
```

---

# PHASE 10 — CMS: Blog, Gallery, Slider, Settings

```
PHASE 10: Content management.

1. Blog admin: rich text editor (TipTap) with sanitized output, cover image + media picker,
   categories/tags, excerpt, scheduled publish, draft preview via signed URL, SEO panel
   (title, description, OG image, canonical, noindex toggle) with a live Google/social preview snippet.
2. Gallery admin: albums, drag-drop multi-upload with progress, bulk edit captions/alt, reorder,
   before/after pairing UI, automatic WebP/AVIF conversions + responsive sizes.
3. **Slider Manager (WordPress-plugin style)**: multiple sliders; slides with drag-order, per-slide
   desktop + mobile image, heading/subheading/CTA, text position picker, animation preset dropdown,
   overlay opacity, duration, schedule (starts/ends); live preview pane; and a shortcode-like slug to
   place a slider on any page.
4. Settings module (grouped tabs, cached in Redis, cache busted on save):
   Business (name, logo, favicon, NAP, socials), Hours & Holidays, Booking rules (slot size, lead time,
   max advance, cancellation window, hold minutes, deposit), Taxes & Currency, Payments,
   Email/SMTP + test-send button, SMS/WhatsApp, Google (OAuth, Calendar, Analytics, Tag Manager,
   Business Profile), SEO defaults, Reminder defaults, Maintenance mode, Security (admin IP allowlist,
   session lifetime, force 2FA).
   Settings writes = super-admin only, fully audit-logged, secrets encrypted and masked in the UI.
5. Media library page: search, filter by type/date, usage tracking ("used in 3 places"),
   safe delete with a warning.
```

---

# PHASE 11 — CRM: Leads, Messages, Reviews, Customers

```
PHASE 11: CRM modules.

1. Leads: auto-created from the contact form, abandoned bookings, and optionally WhatsApp clicks;
   pipeline (new → contacted → qualified → converted → lost) with kanban + list views; assignment;
   notes timeline; follow-up reminders; convert-to-booking action; source attribution + UTM capture;
   duplicate detection by phone/email.
2. Messages inbox: list, read/unread, reply by email from the panel (threaded, logged), spam marking,
   bulk actions, SLA badge (unanswered > 24h).
3. Reviews: collection flow (post-visit email with a signed review link, one review per booking),
   moderation queue (pending/approved/rejected + reason), public admin reply, featured toggle for the
   homepage, cached rating aggregation, AggregateRating JSON-LD, and fake-review guards (must have a
   completed booking, rate limit, IP/device fingerprint check).
4. Customers: 360 profile — contact info, visit history, total spend, favorite services, preferred staff,
   notes (encrypted), tags, simple loyalty points, no-show count, blacklist toggle, and export
   (GDPR-style data export + delete request handling).
5. Segments + basic bulk email/SMS campaigns: filter customers → send template → log + honor unsubscribes.
```

---

# PHASE 12 — POS & Revenue Reporting

```
PHASE 12: POS + reports.

1. POS screen (touch-optimized, keyboard shortcuts, works on tablet): service/product search grid,
   cart with quantity + per-item discount, customer attach (search or quick-create), staff attribution
   per item (for commission), coupon apply, tax, tip, split payment (cash/card/online/wallet),
   change calculator, park/hold sale, refund/void with reason + permission gate.
2. Link a POS sale to an existing booking (checking out a booking marks it completed) or a standalone walk-in.
3. Receipt: printable A4 + 80mm thermal CSS, PDF, email/WhatsApp send, unique invoice number series
   (configurable prefix, gapless).
4. Cash register shifts: open/close with opening float, expected vs counted, variance note, shift report.
5. Reports: revenue (day/week/month/custom, compared to the previous period), by service, by category,
   by staff (with commission calc), by payment method, discounts given, taxes collected, booking funnel
   (created → confirmed → completed / no-show / cancelled), new vs returning customers, peak hours
   heatmap, top customers. All with charts + CSV/XLSX/PDF export.
6. Financial integrity: every money mutation in a transaction, immutable payment records (corrections
   via new rows, not edits), a daily totals reconciliation job, `pos.refund` as a permission separate
   from `pos.sell`, and all refunds audit-logged.
```

---

# PHASE 13 — SEO, Performance, Accessibility, Polish

```
PHASE 13: SEO + performance + a11y.

1. SEO: verify SSR (content visible in view-source), per-route meta component, JSON-LD graph
   (BeautySalon + Service + Offer + Review + Article + Breadcrumb + FAQ), sitemap.xml (auto, including
   services/deals/posts/gallery) + robots.txt, canonical + pagination rel, a 301 redirect map from the
   old Base44 URLs (list them), broken-link scan, GSC + GA4 + GTM wiring (consent-mode aware),
   auto-generated Open Graph images per service/post.
2. Local SEO: a city+service landing page template (`/services/{service}-in-{city}`) with unique content
   blocks, NAP schema, GBP link, embedded map.
3. Performance: Lighthouse audit on all pages → fix to Brief Section 7 targets. Font subsetting +
   `font-display: swap` + preload. Image responsive pipeline audit. Route-based splitting + `React.lazy`.
   Inertia `only:` partial reloads. Redis cache on heavy public queries + HTTP cache headers + optional
   full-page cache for static pages. Bundle analysis report with before/after numbers.
4. Accessibility: WCAG 2.1 AA — keyboard nav for every interactive element (especially the booking
   calendar, lightbox, and slider), focus rings, focus trap in modals, skip link, ARIA for custom widgets,
   contrast fixes, alt text audit, `prefers-reduced-motion` verified, screen-reader pass notes.
5. PWA-lite: manifest, icons, offline fallback page (optional service worker — no aggressive caching of
   authenticated pages).
6. Final UI polish pass: micro-interactions, empty states, error states, loading states, a 404 page with
   search, print styles.

Output: a before/after Lighthouse table + a11y checklist + SEO checklist, all recorded in the state file.
```

---

# PHASE 14 — Testing, Security Audit, Deployment, Handover

```
PHASE 14: Ship it.

1. Test coverage push: Pest feature tests for every controller/action (target ≥80% on app/), Vitest for key
   components, Playwright E2E for: guest booking, logged-in booking, reschedule, cancel, coupon apply,
   POS sale + refund, admin login with 2FA, Excel import, contact form.
2. Load test the booking endpoint (k6/Artillery) — 200 concurrent slot grabs; report p95 and confirm
   zero double-bookings.
3. Full security audit against Brief Section 5: produce a table (control → status → evidence file/test).
   Run `composer audit`, `npm audit`, and a headers scan checklist. OWASP Top 10 walkthrough with our
   specific mitigations.
4. Deployment: production setup guide (Nginx config with security headers + gzip/brotli, PHP-FPM tuning,
   opcache + JIT, supervisor for queue + SSR + Horizon, cron for the scheduler, Redis persistence,
   MySQL tuning, Let's Encrypt, zero-downtime deploy script or Deployer/Envoyer steps, log rotation,
   fail2ban, firewall rules, S3 backups + a restore drill).
5. Monitoring: Sentry, uptime monitor, queue-failure alerts, disk/redis alerts, daily health-check email.
6. Handover docs: `README`, `DEPLOYMENT.md`, `SECURITY.md`, `ADMIN-GUIDE.md` (non-technical, for salon
   staff — with screenshot placeholders), `API.md` if any endpoints are exposed, and a maintenance runbook
   (what to do if the queue stops, if calendar sync breaks, if slots look wrong).
7. Mark `02-PROJECT-STATE.md` complete with a post-launch backlog.
```

---

## Optional Phase 15 — Future backlog (don't build now, just keep the list)
Loyalty program · Gift cards/vouchers · Memberships & packages (10-session cards) · Retail inventory ·
Staff commission payout module · Multi-branch support · Multi-language (Urdu/English) · Mobile app via API ·
WhatsApp booking bot · AI style recommendations.

---

## Prompt add-ons (paste when needed)

**If output gets cut off:**
> Continue exactly where you stopped. Do not re-print earlier files. Continue from `<file path>` line `<n>`.

**Debugging:**
> I'm getting this error: ```<paste>```. Find the root cause — don't guess; ask which files you need first.
> Give me the fix plus a regression test that would catch this bug again.

**Code review:**
> Review this code through exactly four lenses: (1) security holes, (2) N+1 / performance,
> (3) Laravel/React conventions, (4) edge cases. For each finding: severity + file:line + fix.

**At the end of every phase:**
> Now output the full updated version of `02-PROJECT-STATE.md` — refresh Completed, In Progress, Pending,
> Decisions Log, New Env Vars, Known Issues, Next 3 Steps, and the Handoff Block.
