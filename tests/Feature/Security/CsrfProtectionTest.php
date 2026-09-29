<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * Real CSRF rejection cannot be exercised through Laravel's HTTP test client: the framework's own
 * `VerifyCsrfToken::runningUnitTests()` (vendor/laravel/framework/.../VerifyCsrfToken.php:113-115)
 * skips the check entirely whenever `app('env') === 'testing'` — true for every Pest/PHPUnit run, by
 * design (documented, intentional Laravel behavior, not a gap in this test file). A request made
 * through `$this->post(...)` here would return 302 no matter what token it carries, or lack — that
 * would be a false pass, not a real one.
 *
 * So this file does two things instead: (1) structural checks that CSRF verification is actually
 * registered on the `web` middleware group and that the except-list is exactly the one deliberate
 * `csp-report` exemption (Phase 4 sub-step 1) and nothing broader; (2) documents a REAL, live `curl`
 * verification performed against the actual running dev server (not the test runner, so the bypass
 * above doesn't apply) as part of this sub-step:
 *   POST /login, no _token           -> 419
 *   POST /login, wrong _token        -> 419
 *   POST /login, real session token  -> 302 (proceeds into Fortify's own auth logic)
 * Reproducible manually: `php artisan serve`, then repeat the same three requests with curl.
 */
it('registers CSRF verification on the web middleware group', function () {
    $webGroup = app(Kernel::class)->getMiddlewareGroups()['web'] ?? [];

    expect($webGroup)->toContain(ValidateCsrfToken::class);
});

it('exempts only the deliberate csp-report route from CSRF verification', function () {
    $reflection = new ReflectionProperty(ValidateCsrfToken::class, 'neverVerify');
    $reflection->setAccessible(true);

    expect($reflection->getValue())->toBe(['csp-report']);
});
