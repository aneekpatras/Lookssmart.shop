<?php

use App\Support\DebugModeGuard;
use Illuminate\Support\Facades\Artisan;

it('throws when APP_DEBUG is true in the production environment', function () {
    expect(fn () => DebugModeGuard::assertSafe('production', true))
        ->toThrow(RuntimeException::class);
});

it('allows debug mode outside of production', function () {
    DebugModeGuard::assertSafe('local', true);
    DebugModeGuard::assertSafe('testing', true);
    DebugModeGuard::assertSafe('staging', true);
})->expectNotToPerformAssertions();

it('allows production with debug mode off', function () {
    DebugModeGuard::assertSafe('production', false);
})->expectNotToPerformAssertions();

it('can be config-cached without errors', function () {
    $exitCode = Artisan::call('config:cache');

    expect($exitCode)->toBe(0);

    Artisan::call('config:clear');
});
