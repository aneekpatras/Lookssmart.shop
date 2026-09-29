# Deployment Guide — Looks Smart Beauty Salon

Production setup for a single Ubuntu/Debian server running Nginx (or Caddy) + PHP-FPM + MySQL 8 +
Redis 7. Written against what this app actually runs — Laravel 12, Horizon (not a plain `queue:work`
pool — see the Supervisor section), Inertia v2 SSR — not a generic Laravel checklist. Cross-references
`02-PROJECT-STATE.md` for the real decisions/gaps behind each step; read that file's §9/§9a/§10 before
going live.

---

## 1. Prerequisites

- PHP 8.2+ with extensions: `pdo_mysql`, `redis` (or use `predis/predis`, which this app already ships
  with — no `ext-redis` required), `gd` (Intervention Image), `bcmath`, `mbstring`, `xml`, `zip`, `intl`.
- MySQL 8 (or MariaDB 10.11+) with `utf8mb4`.
- Redis 7 — this app's cache, session, queue, and booking slot-locks all run on it (Decision #29). Not
  optional past local dev.
- Node 20+ (build-time only — `npm run build:ssr` produces static assets + an SSR bundle; Node stays
  running afterward only to serve SSR, see §4).
- Composer 2, with `ext-pcntl`/`ext-posix` actually available (a real Linux server has both — the
  `composer.json` platform override pinning them to a fake version, Decision #40, exists only to
  unblock `composer require` on this project's Windows dev machine; a real deploy target needs no
  override and should NOT copy that section's intent, just its presence in the file is harmless).

## 2. Nginx (SSL + HTTP/2)

```nginx
server {
    listen 80;
    server_name lookssmartsalon.example www.lookssmartsalon.example;
    return 301 https://lookssmartsalon.example$request_uri;
}

server {
    listen 443 ssl http2;
    server_name lookssmartsalon.example;
    root /var/www/looks-smart-salon/public;

    ssl_certificate     /etc/letsencrypt/live/lookssmartsalon.example/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/lookssmartsalon.example/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

    index index.php;
    charset utf-8;

    # Vite-built assets — long-cache, immutable (filenames are content-hashed)
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

**Check the actual PHP-FPM socket path** before using this — `php -v` for the installed version, then
`ls /run/php/` to confirm the exact socket filename (`php8.2-fpm.sock`, `php8.3-fpm.sock`, etc.); it
must match the PHP version this app is actually running under.

`X-Frame-Options`/CSP/`Referrer-Policy`/`Permissions-Policy`/`nosniff` are deliberately NOT set here —
`app/Http/Middleware/SecurityHeaders.php` already sets the full header set on every response at the
application layer (Phase 14 sub-step 1), including error pages. Setting them again in Nginx would be
redundant at best and a source of drift (two places to keep in sync) at worst.

## 3. Caddy (alternative — automatic HTTPS, no certbot needed)

```
lookssmartsalon.example {
    root * /var/www/looks-smart-salon/public
    encode gzip

    @assets path /build/*
    header @assets Cache-Control "public, immutable, max-age=31536000"

    php_fastcgi unix//run/php/php8.2-fpm.sock
    file_server
}
```

Caddy handles the ACME/TLS renewal itself — no cron job needed for certificate rotation (unlike
certbot in the Nginx setup, which needs its own renewal timer, usually installed automatically by the
`certbot` package as a systemd timer — verify with `systemctl list-timers | grep certbot`).

## 4. Processes to supervise

This app has **three** long-running processes in production, not the generic Laravel two:

| Process | Command | Why |
|---|---|---|
| Horizon | `php artisan horizon` | Runs and supervises the actual queue workers itself — **not** a `queue:work` pool. Horizon IS this app's queue worker; do not also run `queue:work` alongside it. |
| Inertia SSR | `php artisan inertia:start-ssr` | Server-side rendering for the public site (SEO — Phase 13). |
| PHP-FPM | (usually its own systemd service already) | Serves the actual HTTP requests. |

### Supervisor config

`/etc/supervisor/conf.d/looks-smart-salon-horizon.conf`:
```ini
[program:looks-smart-salon-horizon]
process_name=%(program_name)s
command=php /var/www/looks-smart-salon/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/looks-smart-salon/storage/logs/horizon.log
stopwaitsecs=3600
```

`stopwaitsecs=3600` matches Horizon's own documented recommendation — it needs time to let in-flight
jobs finish before Supervisor sends `SIGKILL`.

`/etc/supervisor/conf.d/looks-smart-salon-ssr.conf`:
```ini
[program:looks-smart-salon-ssr]
process_name=%(program_name)s
command=php /var/www/looks-smart-salon/artisan inertia:start-ssr
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/looks-smart-salon/storage/logs/ssr.log
```

After adding either file: `supervisorctl reread && supervisorctl update && supervisorctl start looks-smart-salon-horizon looks-smart-salon-ssr`.

### Cron (the actual scheduler — drives 7 real scheduled commands)

One line, `crontab -e` as `www-data` (or the deploy user PHP-FPM runs as):
```
* * * * * cd /var/www/looks-smart-salon && php artisan schedule:run >> /dev/null 2>&1
```

What this one line actually drives (`routes/console.php`) — no separate cron entry needed per command,
Laravel's scheduler dispatches all of them from this single minutely tick:

| Command | Schedule |
|---|---|
| `backup:run` | daily at 02:00 |
| `backup:clean` | daily at 01:30 |
| `backup:monitor` | daily at 03:00 |
| `app:send-staff-daily-schedules` | daily at 07:00 |
| `bookings:send-reminders` | every 5 minutes |
| `bookings:request-reviews` | daily at 10:00 |
| `posts:publish-scheduled` | every 5 minutes |

## 5. Redis

```ini
# /etc/redis/redis.conf — the parts that matter for this app
maxmemory 512mb
maxmemory-policy noeviction   # NOT allkeys-lru — this app stores booking slot-holds and rate-limit
                              # counters in Redis; evicting a slot-hold key early would silently let
                              # a double-booking through. Session/cache data can tolerate eviction,
                              # slot-holds/rate-limits cannot, and Redis has no per-key policy — pick
                              # the safer default and size maxmemory generously instead.
requirepass <a real generated password>
```

`.env`:
```
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=<same password as above>
REDIS_PORT=6379
REDIS_CLIENT=predis
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

`REDIS_CLIENT=predis` (not `phpredis`) — this app has always run on `predis/predis` (Decision #29,
no `ext-redis` on the dev machine); a production server with `ext-redis` compiled in could switch to
`REDIS_CLIENT=phpredis` for a performance gain, but that's an optional follow-up, not a requirement —
verify with a real load test before switching, don't assume.

Rate limiting (`RateLimiter::for(...)` in `AppServiceProvider`) and booking slot-holds
(`SlotHoldService`) both use the default cache store — confirm `CACHE_STORE=redis` is actually set
before going live, since the login/register/password-reset throttles and the double-booking guard's
UX-level hold both silently degrade to file-based (or whatever `CACHE_STORE` actually is) if this is
missed.

## 6. Sentry

`sentry/sentry-laravel` is installed and wired (`bootstrap/app.php`, `config/sentry.php`) but genuinely
inert until a DSN is set — no code changes needed to activate it, just:
```
SENTRY_LARAVEL_DSN=https://<key>@<org>.ingest.sentry.io/<project>
SENTRY_TRACES_SAMPLE_RATE=0.1
```
(Drop `SENTRY_TRACES_SAMPLE_RATE` from `1.0`, the pre-launch default, to something like `0.1` once
real traffic exists — 100% transaction tracing on a live site is expensive and unnecessary.)

`config/sentry.php`'s `before_send` already scrubs `Authorization`/`Cookie` headers and
password/token-shaped request fields (`App\Support\SentryPiiScrubber`) before anything is sent —
`send_default_pii` is also `false`. No additional PII configuration is needed to go live safely.

**Not included**: browser-side (React) error tracking. `@sentry/react` was deliberately not installed
this sub-step (§10 #21) — a client-side JS crash currently reports nowhere. Add it separately if
browser-error visibility is wanted; it's an independent package/wiring effort, not a config flip.

## 7. Backups

Already fully built and scheduled (`spatie/laravel-backup`, `config/backup.php`, §5/§9 of the state
file) — nothing to build here, only to configure for production:
```
BACKUP_DISKS=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...
BACKUP_ARCHIVE_PASSWORD=<a real generated password — enables AES-256 encryption>
BACKUP_NOTIFICATION_EMAIL=<a real address someone actually reads>
```
Local dev defaults to `BACKUP_DISKS=local`, which is fine for a dry run but is not off-site — production
MUST set `s3` (or another real off-site disk) before the first scheduled `backup:run` fires, or backups
will exist only on the same server they're protecting against.

**Known gap, not fixed by this guide (§9's own backup row, and §10 #19)**: encryption has been
verified real (a real zip only opens with the correct password), but a full **restore** has never been
rehearsed even once — only backup *creation* has been tested. Before this goes live, actually restore
one backup into a scratch database and confirm the app boots against it. Skipping this step means the
backup strategy is unverified exactly where it matters most — the day something actually needs restoring.

## 8. Production `.env` checklist

Beyond the DB/Redis/Sentry/backup values above:
```
APP_ENV=production
APP_DEBUG=false          # DebugModeGuard refuses to boot if this is true in production — a real gate, not just a reminder
APP_URL=https://lookssmartsalon.example   # must match the real host+port — signed URLs (guest booking links, invites) fail verification otherwise (§10 #24)
SESSION_SECURE_COOKIE=true                # also now defaults to true in production if left unset (Phase 14 sub-step 2) — but set it explicitly anyway
TRUSTED_PROXIES=127.0.0.1                 # same-host Nginx; use the load balancer's real IP/CIDR instead for a multi-host deploy — never '*'
MAIL_MAILER=<a real provider — Postmark/Resend, not log>
HASH_DRIVER=argon2id                      # already the default; don't change it
```

## 9. Deploy steps

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci
npm run build:ssr
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
supervisorctl restart looks-smart-salon-horizon looks-smart-salon-ssr
```

`--force` on `migrate` is required in production (Laravel refuses destructive-looking migrations
without it in a non-local environment) — this is Laravel protecting you, not a flag to add blindly;
always know what a migration does before running this.

Restarting Horizon (not just reloading Nginx/PHP-FPM) is required after any deploy that touched queued
job code — PHP-FPM picks up new code on the next request automatically, but Horizon's already-running
worker processes keep the OLD code loaded in memory until restarted.

## 10. Handover checklist

- [ ] Real credentials rotated in from whoever held them during development (DB password, `BACKUP_ARCHIVE_PASSWORD`, `APP_KEY` regenerated fresh with `php artisan key:generate --force`, Sentry DSN, mail provider key, Google/Twilio/Turnstile keys if those integrations are turned on)
- [ ] `APP_KEY` is a NEW value for production — never reuse the dev `.env`'s key (it decrypts every encrypted column: PII casts, session cookies, Setting secrets)
- [ ] A real backup restore has been rehearsed at least once (§7 above) — not just backup creation
- [ ] `TRUSTED_PROXIES` is set to the real proxy IP, not left blank (session security / rate-limit-by-IP both depend on it — §9's "trusted proxies" row)
- [ ] DNS + SSL certificate are live and auto-renewing (certbot timer, or Caddy's automatic handling)
- [ ] Horizon dashboard (`/horizon`) is reachable only to the intended emails — its own gate is in `app/Providers/HorizonServiceProvider.php`; confirm the allowlist reflects real production admins, not dev placeholders
- [ ] Whoever is on call knows: Sentry is where exceptions surface (§6), Horizon's dashboard is where queue health lives, `storage/logs/laravel.log` is the fallback if Sentry itself is misconfigured
- [ ] Known, tracked gaps at handover time (not secrets, just things the next person shouldn't be surprised by) — see `02-PROJECT-STATE.md` §10 in full, especially: no Playwright/Vitest browser-level E2E exists (#36), no load test of the booking endpoint has been run, backup restore is unrehearsed (above), Dependabot isn't configured (#20), browser-side Sentry isn't wired (#21)
