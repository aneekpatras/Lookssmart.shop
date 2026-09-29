<?php

use App\Http\Controllers\Auth\InviteAcceptController;
use App\Http\Controllers\SessionsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Auth-adjacent routes (Phase 3)
|--------------------------------------------------------------------------
|
| Fortify itself registers /login, /register, /logout, /forgot-password, /reset-password,
| /email/verify, /user/confirm-password, /user/two-factor-authentication, etc. — these are the
| pieces the Brief asks for that Fortify does not provide out of the box.
*/

// Phase 5 sub-step 4: signed admin-invite acceptance. `guest`-only (an already-authenticated user
// accepting someone else's invite would be a confusing account-mixing edge case, not a real use
// case) + `signed` (rejects a tampered/expired link before the controller ever runs). GET and POST
// share the exact same path/name-prefix so the signature — tied to path+query, not HTTP method —
// validates identically for both; the accept form POSTs back to the current (still-signed) URL.
Route::middleware(['guest', 'signed'])->group(function () {
    Route::get('/invite/accept', [InviteAcceptController::class, 'show'])->name('invite.accept.show');
    Route::post('/invite/accept', [InviteAcceptController::class, 'store'])->name('invite.accept.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/two-factor-setup', fn () => Inertia::render('Auth/TwoFactorSetup'))
        ->name('two-factor.setup');

    Route::get('/user/sessions', [SessionsController::class, 'index'])->name('sessions.index');
    Route::delete('/user/sessions/other', [SessionsController::class, 'destroyOthers'])
        ->name('sessions.destroy-others');
});
