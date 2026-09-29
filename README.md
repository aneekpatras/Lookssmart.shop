# Looks Smart Beauty Salon

Custom booking + salon management platform. Public marketing site, online booking engine, and a
full admin dashboard (bookings, POS, CRM, CMS, reports). Rebuilt from scratch (previously Base44
no-code) with security as a first-class concern.

Full spec lives in `00-PROJECT-BRIEF.md`, `01-PHASE-PROMPTS.md`, and `02-PROJECT-STATE.md`.

## Requirements

**Local dev on this machine runs natively (no Docker/Sail) — see `02-PROJECT-STATE.md` Decision #8.**

- PHP 8.2+ (Brief targets 8.3 for staging/production; 8.2 is what's actually installed locally via
  XAMPP, and Laravel 11 supports both — Decision #9), with extensions: `gd`, `intl`, `zip`, `curl`,
  `fileinfo`, `mbstring`, `exif`, `mysqli`, `pdo_mysql`, `openssl`
- Composer 2
- Node 20+, npm
- MySQL 8 or MariaDB 10.11+ (locally: XAMPP's bundled MariaDB — see Decision #11 for the version
  caveat)
- Git
- Redis — **not required until Phase 7** (booking engine slot-locks depend on it); see State file
  §10 #6 for the hard gate and install options when that phase starts

## Target repo tree (post Phase 1 scaffold)

```
├── app/
│   ├── Actions/            # business actions (CreateBookingAction, etc.)
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Requests/       # all validation (FormRequest per endpoint)
│   ├── Models/
│   ├── Policies/           # authorization, one per model
│   └── Services/           # AvailabilityEngine, GoogleCalendarService, TurnstileService, ...
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── public/
├── resources/
│   ├── js/
│   │   ├── Components/
│   │   │   └── ui/         # shadcn primitives
│   │   ├── Layouts/         # PublicLayout, AdminLayout
│   │   ├── Pages/
│   │   │   ├── Public/
│   │   │   └── Admin/
│   │   └── app.tsx
│   └── views/
│       └── app.blade.php
├── routes/
│   ├── web.php
│   └── admin.php
├── storage/
├── tests/
│   ├── Feature/
│   │   └── Security/
│   └── Unit/
├── .github/workflows/ci.yml
├── docker-compose.yml
├── .env.example
├── pint.json
├── eslint.config.js
├── tsconfig.json
└── vite.config.ts
```

## Local setup (native — no Docker)

```bash
# 1. Database: create it once (XAMPP MySQL/MariaDB, root / empty password by default)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS looks_smart_salon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Env
cp .env.example .env        # Windows: copy .env.example .env

# --- from Phase 1 onward, once the Laravel app is scaffolded ---
composer install
php artisan key:generate
npm install

php artisan migrate:fresh --seed
npm run dev                  # Vite dev server (leave running in its own terminal)
php artisan serve            # Laravel app server (separate terminal)

# quality gates
php artisan test             # Pest
./vendor/bin/pint
npm run lint && npm run typecheck
```

Local URLs: whatever `php artisan serve` prints (default `http://127.0.0.1:8000`) · `/admin`.
`/horizon` only becomes functional once Redis is installed (Phase 7+, see State file §10 #6) — Horizon
requires a Redis queue connection to run.

`docker-compose.yml` is kept in the repo but **unused** for local dev on this machine — see
`02-PROJECT-STATE.md` Decision #8. It's a future/CI reference only (e.g. a machine with more RAM, or a
future containerized staging setup), not part of the current workflow.

## Phase 1 handoff — scaffolding into a non-empty directory

`composer create-project laravel/laravel .` refuses to run into a directory that already has files
in it (this repo already has the Phase 0 docs/tooling/CI at the root). Use the scratch-folder +
merge procedure instead:

1. **Scaffold into a scratch folder, next to this repo (not inside it):**
   ```bash
   cd ..
   composer create-project laravel/laravel looks-smart-scaffold
   cd looks-smart-scaffold
   composer require inertiajs/inertia-laravel laravel/horizon tightenco/ziggy
   # NOTE: laravel/sail intentionally omitted — local dev is native (no Docker), see Decision #8.
   # laravel/horizon is installed now but stays dormant until Redis exists (Phase 7+, State file §10 #6).
   php artisan inertia:middleware
   npm create vite@latest . -- --template react-ts   # or install React + Inertia adapter directly
   npm install @inertiajs/react react react-dom
   npx shadcn@latest init
   ```
2. **Copy the generated Laravel/Inertia/React application code into this repo**, file by file,
   *without* overwriting anything already present at the root (`.gitignore`, `.env.example`,
   `docker-compose.yml`, `pint.json`, `eslint.config.js`, `tsconfig.json`, `.editorconfig`,
   `.prettierrc.json`, `.github/`, `GIT-STRATEGY.md`, `README.md`, `CLAUDE.md`, `START-HERE.md`,
   the three `0X-*.md` files, `.claude/`). Everything else (`app/`, `bootstrap/`, `config/`,
   `database/`, `public/`, `resources/`, `routes/`, `artisan`, `composer.json`, `package.json`,
   `vite.config.ts`, ...) merges straight in.
3. **Reconcile config files** the scaffold also generates (it will create its own
   `composer.json`, `package.json`, `vite.config.ts`, and a default `.gitignore`/`.editorconfig`) —
   keep the scaffold's `composer.json`/`package.json`/`vite.config.ts` (add our packages to them),
   but keep **this repo's** `.gitignore`, `.editorconfig`, `.prettierrc.json`, `eslint.config.js`,
   `tsconfig.json`, and `pint.json` since they're already tailored to this project's conventions.
4. Delete the scratch folder once the merge is confirmed working
   (`php artisan serve` boots, `npm run build` succeeds).
5. Run `php artisan key:generate`, then `composer install && npm install` from the repo root to
   confirm everything resolves in place.

This procedure is only needed once, for Phase 1. Every phase after that works directly in this
repo like a normal Laravel project.
