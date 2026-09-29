<?php

use Illuminate\Support\Facades\Route;

/**
 * Phase 14 sub-step 1: dedicated, comprehensive coverage for the header set as a whole (previously
 * only the CSP half of this was directly tested — `XssPayloadTest.php`) plus the two other Brief §5
 * production-safety controls this sub-step's spec calls out by name: the local-only dev-login route
 * and stack-trace suppression on a genuine unhandled error.
 */
it('sends the full required security header set on a real public response', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy');
    $response->assertHeader('Content-Security-Policy');

    // DENY (not SAMEORIGIN) deliberately kept — this app never frames itself, so DENY is strictly
    // safer with no functional cost; matches the CSP's own frame-ancestors 'none'/frame-src default.
    expect($response->headers->get('Permissions-Policy'))
        ->toContain('camera=()')
        ->toContain('microphone=()')
        ->toContain('geolocation=()');
});

it('sends the same security headers on an unauthenticated admin redirect, not only on 200 responses', function () {
    $response = $this->get('/admin');

    $response->assertRedirect();
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Content-Security-Policy');
});

it('sends security headers on a 404 for a path that matches no route at all', function () {
    // A DIFFERENT case than the admin-redirect one above: that request matched a real route (whose
    // OWN route-level middleware threw), so it's covered by SecurityHeaders' try/catch. A path
    // matching no route at all never reaches SecurityHeaders (or any route middleware) in the first
    // place — Laravel's router raises its own 404 before route middleware resolution runs — so this
    // is covered by the separate fallback in bootstrap/app.php's exception renderer instead. Every
    // bot/scanner probe of a nonexistent path (/wp-admin, /.env, etc.) is exactly this case.
    $response = $this->get('/this-path-does-not-exist-anywhere');

    $response->assertNotFound();
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Content-Security-Policy');
});

it('does not register the local-only dev-login route outside the local environment', function () {
    expect(Route::has('dev-login'))->toBeFalse();

    $this->get('/admin/dev-login')->assertNotFound();
});

it('never leaks a stack trace or exception message when APP_DEBUG is off, the required production setting', function () {
    // The custom Inertia-aware error-page branch in bootstrap/app.php is itself bypassed during
    // automated tests (`app()->environment('testing')` short-circuits it) — this deliberately tests
    // what actually runs in that case: Laravel's own core exception rendering, which is exactly what
    // a production APP_DEBUG=false request hits regardless of the Inertia-specific branch on top of
    // it. `.env`'s local APP_DEBUG=true (correct for day-to-day development) is overridden here so
    // this test reflects the production-required setting, not this machine's dev convenience value.
    config(['app.debug' => false]);

    Route::get('/__test-only-throws', fn () => throw new RuntimeException('sensitive-internal-detail-db-password-hunter2'));

    $inertiaResponse = $this->get('/__test-only-throws', ['X-Inertia' => 'true']);
    $inertiaResponse->assertStatus(500);
    $inertiaResponse->assertDontSee('sensitive-internal-detail', false);
    $inertiaResponse->assertDontSee('RuntimeException', false);

    $plainResponse = $this->get('/__test-only-throws');
    $plainResponse->assertStatus(500);
    $plainResponse->assertDontSee('sensitive-internal-detail', false);
    $plainResponse->assertDontSee('RuntimeException', false);
});
