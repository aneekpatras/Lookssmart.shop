<?php

use App\Http\Controllers\CspReportController;
use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\NotificationUnsubscribeController;
use App\Http\Controllers\PublicWebsiteController;
use App\Http\Controllers\SeoController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes (Brief §3 — Public Website)
|--------------------------------------------------------------------------
|
| Shells only for Phase 1 — full implementations land in Phase 9. Route names are prefixed to make
| Ziggy's `route()` helper unambiguous once admin routes are added.
*/

Route::get('/', [PublicWebsiteController::class, 'home'])->name('home');

// Phase 13 sub-step 1. `public/robots.txt` was deleted — a physical file in `public/` is served
// directly (by the webserver, and by `artisan serve`'s own built-in router) before any Laravel route
// is ever reached, so the dynamic route below would otherwise be permanently unreachable.
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
// Phase 13 sub-step 2.
Route::get('/manifest.json', [SeoController::class, 'manifest'])->name('manifest');

Route::get('/gallery', [PublicWebsiteController::class, 'gallery'])->name('gallery.index');

Route::get('/notifications/unsubscribe/{user}/{channel}', NotificationUnsubscribeController::class)
    ->middleware('signed')
    ->name('notifications.unsubscribe');
Route::get('/about', [PublicWebsiteController::class, 'about'])->name('about');
Route::get('/services', [PublicWebsiteController::class, 'services'])->name('services.index');
Route::get('/services/{slug}', [PublicWebsiteController::class, 'detail'])->name('services.show');
Route::get('/deals', [PublicWebsiteController::class, 'deals'])->name('deals.index');
Route::get('/blog', [PublicWebsiteController::class, 'blog'])->name('blog.index');
Route::get('/blog/{slug}', [PublicWebsiteController::class, 'blogShow'])->name('blog.show');
Route::get('/contact', [PublicWebsiteController::class, 'contact'])->name('contact');
Route::post('/contact', [PublicWebsiteController::class, 'submitContact'])
    ->middleware('throttle:contact')
    ->name('contact.submit');
Route::post('/reviews', [PublicWebsiteController::class, 'submitReview'])
    ->middleware('throttle:review')
    ->name('reviews.submit');
Route::get('/book', [PublicWebsiteController::class, 'booking'])->name('book');
Route::get('/privacy-policy', [PublicWebsiteController::class, 'privacyPolicy'])->name('privacy-policy');

// Customer portal (Phase 3) — profile/password/2FA/sessions now; "my bookings" is an empty state
// until Phase 7 (Booking Engine) exists. Guests get magic-link booking access instead of an
// account — see 02-PROJECT-STATE.md Decision #21 (implemented in Phase 7).
Route::middleware('auth')->group(function () {
    Route::get('/my-account', [CustomerDashboardController::class, 'index'])->name('my-account');
    Route::get('/my-bookings', [CustomerDashboardController::class, 'bookings'])->name('my-bookings');
    Route::put('/my-account/preferences', [CustomerDashboardController::class, 'updatePreferences'])
        ->name('my-account.preferences');
});

Route::post('/csp-report', [CspReportController::class, 'store'])->name('csp-report');

/*
| Phase 12 sub-step 3: local-only dev-login. Wrapped in an environment() check at ROUTE-REGISTRATION
| time (not just an in-handler guard) so the route itself does not exist unless APP_ENV=local — there
| is no runtime toggle or request input that can ever make this reachable in production/staging.
| Logs straight in as a real seeded admin (real session, same as
| a normal login) purely to skip Fortify's password + Google OAuth + TOTP flow during local
| development/testing of the admin dashboard. Still runs through every Policy/permission check as
| normal — the credentials login-time gate is skipped, and only for this session.
|
// TODO: REMOVE_DEV_AUTH_BYPASS — this route is dev convenience only. Delete it before a production
// launch, even though it is already environment-gated and inert outside local.
*/
if (app()->environment('local')) {
    Route::get('/admin/dev-login', function () {
        $user = User::role(['super-admin', 'admin'])->first() ?? User::first();

        abort_unless($user, 404, 'No user exists to log in as — seed the database first.');

        Auth::login($user);

        return redirect()->route('admin.dashboard');
    })->name('dev-login');
}

require __DIR__ . '/auth.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/booking.php';
