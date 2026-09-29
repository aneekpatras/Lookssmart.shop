<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

uses(RefreshDatabase::class);

/**
 * Phase 14 sub-step 2. Covers 3 production-readiness fixes that don't fit an existing test file:
 * the session secure-cookie default, TrustProxies/HTTPS-forcing wiring, and Sentry being inert
 * with no DSN configured. Config files (session.php) are evaluated once at boot, so the
 * production-specific branch can't be exercised by swapping APP_ENV mid-test the way a normal
 * service call can — the SESSION_SECURE_COOKIE case below tests the exact `env(key, fallback)`
 * expression shape used in config/session.php against a real, unused env key instead of the real
 * one (which this repo's .env sets explicitly, so it would never hit the fallback in any test run).
 */
it('resolves the env()-with-environment-fallback pattern correctly for production vs non-production', function () {
    // config/session.php reads the raw APP_ENV env var directly (env('APP_ENV') === 'production'),
    // NOT app()->environment() — deliberately, since app()->environment() crashes `artisan
    // package:discover` (config files load before the container's 'env' binding exists in that
    // minimal bootstrap; found and fixed this same sub-step). So this test manipulates the actual
    // OS-level env var, not the container binding, to match what the config file really reads.
    $key = 'LS_TEST_ENV_FALLBACK_' . uniqid();
    $originalAppEnv = getenv('APP_ENV');

    putenv('APP_ENV=production');
    $_ENV['APP_ENV'] = 'production';
    expect(env($key, env('APP_ENV') === 'production'))->toBeTrue();

    putenv('APP_ENV=testing');
    $_ENV['APP_ENV'] = 'testing';
    expect(env($key, env('APP_ENV') === 'production'))->toBeFalse();

    putenv($originalAppEnv === false ? 'APP_ENV' : "APP_ENV={$originalAppEnv}");
    $_ENV['APP_ENV'] = $originalAppEnv;
});

it('does not force the secure session cookie flag in the current (non-production) environment', function () {
    expect(config('session.secure'))->toBeFalse();
});

it('boots and serves a real page cleanly with Sentry wired but no DSN configured', function () {
    // Sentry's PHP SDK treats an empty DSN as disabled, same as null — this .env ships
    // SENTRY_LARAVEL_DSN= (blank, not unset), which env() resolves to '' rather than null. Either
    // way this proves the app doesn't error simply because sentry/sentry-laravel is now a
    // dependency, without needing a real DSN.
    expect(config('sentry.dsn'))->toBeEmpty();

    $this->get('/')->assertOk();
});

it('ignores a spoofed X-Forwarded-For header when no proxy is configured as trusted', function () {
    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '10.0.0.1']);
    $request->headers->set('X-Forwarded-For', '1.2.3.4');

    $middleware = app(TrustProxies::class);
    $resolvedIp = null;
    $middleware->handle($request, function ($req) use (&$resolvedIp) {
        $resolvedIp = $req->ip();

        return new Response;
    });

    // With TRUSTED_PROXIES empty (this app's local/default state), the spoofed header must be
    // ignored entirely — the real connecting address is what's trusted, not anything a client claims.
    expect($resolvedIp)->toBe('10.0.0.1');
});
