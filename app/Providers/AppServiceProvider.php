<?php

namespace App\Providers;

use App\Support\DebugModeGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // intervention/image v3 dropped its Laravel facade/provider into a separate
        // (unused) package — bind the manager ourselves. GD, not Imagick: confirmed via
        // `php -m` as the extension actually available on this machine (see §10 of the state file).
        $this->app->singleton(ImageManager::class, fn () => new ImageManager(new Driver));
    }

    public function boot(): void
    {
        DebugModeGuard::assertSafe($this->app->environment(), (bool) config('app.debug'));

        // Phase 14 sub-step 2: a production deploy sits behind Nginx/a load balancer terminating
        // TLS, forwarding plain HTTP to PHP-FPM — without this, $request->secure() (and everything
        // that depends on it: the session 'secure' flag above, SecurityHeaders' HSTS header,
        // signed-URL scheme validation) sees every request as insecure. bootstrap/app.php's
        // trustProxies() call is the other half of this — trusting the proxy's X-Forwarded-Proto
        // header is what makes secure() true in the first place; this just makes URL::to()/route()
        // generate https:// links to match, since nothing upstream of Nginx tells Laravel that on
        // its own.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Brief §5 / Phase 4 item 11 (mass assignment): a mass-assignment attempt against a
        // non-fillable attribute now THROWS instead of being silently dropped — surfaces both bugs
        // (forgot to add a field to $fillable) and attacks (a request carrying an unexpected key)
        // immediately in dev/test. Guarded to non-production so a stray/malicious extra key never
        // takes a real production request down; production keeps Eloquent's normal silent-discard.
        // Deliberately NOT the broader `Model::shouldBeStrict()` — that also enables
        // `preventLazyLoading`, which hasn't been audited across the existing Inertia pages/relations
        // yet and would risk breaking unrelated working code for a change this sub-step didn't ask for.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // LogSuccessfulLogin, LogLockout, and LogNotificationDispatch are picked up by Laravel 11's
        // automatic event discovery (a `handle()` method typed to the event is enough) — do NOT also
        // register them here with Event::listen(). Doing both was a real bug found in Phase 7: every
        // listener ran TWICE per event (confirmed via `artisan event:list`), meaning every suspicious-
        // login email, lockout notification, and notification_logs row had been silently duplicated
        // since Phase 3. Fixed by removing the redundant explicit registration, not by disabling
        // discovery — discovery is what the rest of the Laravel 11 app already assumes is active.

        $this->registerRateLimiters();
    }

    /**
     * Brief §5 / Phase 4 item 2. `login`/`two-factor` are already defined in
     * FortifyServiceProvider (Fortify reads those two by name from config/fortify.php).
     *
     * `register`/`password-reset` are enforced via the `fortify-routes` limiter below, applied as a
     * GLOBAL `web` middleware (bootstrap/app.php) rather than by attaching `throttle:...` to
     * Fortify's already-registered routes directly — that route-mutation approach was tried first
     * and empirically DID NOT take effect (verified via `route:list -v`, middleware never appeared).
     * `Limit::none()` makes this a no-op for every route that isn't register/password-reset, so it's
     * safe to apply globally.
     *
     * `contact`/`booking`/`review`/`admin-write` have no routes yet (Phases 7/9/11/5+) — defined
     * now so those phases only need to add `->middleware('throttle:<name>')`, not redesign limits.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('fortify-routes', function (Request $request) {
            return match ($request->route()?->getName()) {
                // The POST route Fortify actually names 'register.store' — 'register' (GET) is just
                // the view. Confirmed via `artisan route:list --name=register`, not assumed.
                'register.store' => Limit::perMinutes(15, 5)->by($request->ip()),
                'password.email', 'password.update' => Limit::perMinutes(15, 5)->by(
                    $request->ip() . '|' . (string) $request->input('email'),
                ),
                default => Limit::none(),
            };
        });

        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('booking', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        // Decision #21 — defense in depth on top of the signed URL's HMAC for the guest magic-link.
        RateLimiter::for('booking-manage', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('review', fn (Request $request) => Limit::perMinutes(15, 5)->by($request->ip()));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by(
            $request->user()?->id ?: $request->ip(),
        ));

        RateLimiter::for('admin-write', fn (Request $request) => Limit::perMinute(30)->by(
            $request->user()?->id ?: $request->ip(),
        ));
    }
}
