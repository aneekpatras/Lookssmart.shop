<?php

use App\Http\Controllers\Admin\AdminBookingController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AvailabilityController;
use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DealController;
use App\Http\Controllers\Admin\GalleryCategoryController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\HeroSliderController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\MediaLibraryController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\NotificationLogController;
use App\Http\Controllers\Admin\PosCheckoutController;
use App\Http\Controllers\Admin\PosRegisterController;
use App\Http\Controllers\Admin\PosSalesHistoryController;
use App\Http\Controllers\Admin\PostCategoryController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\ServiceImportController;
use App\Http\Controllers\Admin\ServicePriceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Admin routes (Brief §3 — Admin Dashboard)
|--------------------------------------------------------------------------
|
| Page bodies are shells until Phases 5-12. Gated behind `auth` + `verified` + `role`
| (spatie/laravel-permission — Brief §2's access table: super-admin/admin/receptionist/staff only;
| `customer`/`guest` never reach `/admin/*` at all — audit finding, 2026-08-26 fix). This is a
| stopgap ROUTE-level check, not per-action `->authorize()` calls against the Policy layer built in
| Phase 4 — that granular wiring is still Phase 5/6's job. See 02-PROJECT-STATE.md §10 #9.
*/

Route::prefix('admin')
    ->name('admin.')
    ->middleware([
        'auth',
        'verified',
        'role:super-admin|admin|receptionist|staff',
    ])
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        // Phase 14 sub-step 2 — real Booking Engine (Phase 7) integration, replacing the placeholder.
        Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings');
        Route::get('/bookings/{booking}', [AdminBookingController::class, 'show'])->name('bookings.show');
        Route::patch('/bookings/{booking}/status', [AdminBookingController::class, 'updateStatus'])->name('bookings.status');
        Route::delete('/bookings/{booking}', [AdminBookingController::class, 'destroy'])->name('bookings.destroy');
        Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability');
        Route::post('/availability/working-hours', [AvailabilityController::class, 'storeWorkingHour'])->name('availability.working-hours.store');
        Route::delete('/availability/working-hours/{working_hour}', [AvailabilityController::class, 'destroyWorkingHour'])->name('availability.working-hours.destroy');
        Route::post('/availability/time-off', [AvailabilityController::class, 'storeTimeOff'])->name('availability.time-off.store');
        Route::delete('/availability/time-off/{time_off}', [AvailabilityController::class, 'destroyTimeOff'])->name('availability.time-off.destroy');
        // Phase 12 sub-step 2.
        Route::get('/pos', [PosRegisterController::class, 'status'])->name('pos');
        Route::post('/pos/register/open', [PosRegisterController::class, 'open'])->name('pos.register.open');
        Route::post('/pos/register/{shift}/cash-in', [PosRegisterController::class, 'addCash'])->name('pos.register.cash-in');
        Route::post('/pos/register/{shift}/cash-out', [PosRegisterController::class, 'removeCash'])->name('pos.register.cash-out');
        Route::post('/pos/register/{shift}/close', [PosRegisterController::class, 'close'])->name('pos.register.close');
        Route::get('/pos/register/{shift}/z-report', [PosRegisterController::class, 'zReport'])->name('pos.register.z-report');
        // Phase 12 sub-step 3.
        Route::get('/pos/terminal', [PosCheckoutController::class, 'terminal'])->name('pos.terminal');
        Route::get('/pos/search/services', [PosCheckoutController::class, 'searchServices'])->name('pos.search.services');
        Route::get('/pos/search/deals', [PosCheckoutController::class, 'searchDeals'])->name('pos.search.deals');
        Route::get('/pos/search/customers', [PosCheckoutController::class, 'searchCustomers'])->name('pos.search.customers');
        Route::post('/pos/customers', [PosCheckoutController::class, 'quickCreateCustomer'])->name('pos.customers.quick-create');
        Route::post('/pos/apply-coupon', [PosCheckoutController::class, 'applyCoupon'])->name('pos.apply-coupon');
        Route::post('/pos/checkout', [PosCheckoutController::class, 'checkout'])->name('pos.checkout');
        Route::get('/pos/sales/{sale}/receipt', [PosCheckoutController::class, 'receipt'])->name('pos.sales.receipt');
        // Phase 12 sub-step 4 — Held Sales, Void, Sales History, A4/thermal invoicing.
        Route::post('/pos/hold', [PosCheckoutController::class, 'hold'])->name('pos.hold');
        Route::get('/pos/held-sales', [PosCheckoutController::class, 'heldSales'])->name('pos.held-sales');
        Route::delete('/pos/held-sales/{sale}', [PosCheckoutController::class, 'discardHold'])->name('pos.held-sales.discard');
        Route::get('/pos/sales-history', [PosSalesHistoryController::class, 'index'])->name('pos.sales-history');
        Route::get('/pos/sales-history/export', [PosSalesHistoryController::class, 'exportCsv'])->name('pos.sales-history.export');
        // `withTrashed()`: a voided sale is soft-deleted, but its invoice must stay viewable (Sales
        // History links to it) and re-voiding it must 409 with a clear message rather than 404 as if
        // it never existed.
        Route::get('/pos/sales/{sale}', [PosCheckoutController::class, 'show'])->name('pos.sales.show')->withTrashed();
        Route::get('/pos/sales/{sale}/invoice-pdf', [PosCheckoutController::class, 'invoicePdf'])->name('pos.sales.invoice-pdf')->withTrashed();
        Route::post('/pos/sales/{sale}/void', [PosCheckoutController::class, 'void'])->name('pos.sales.void')->withTrashed();
        // Phase 12 sub-step 1.
        Route::get('/reports', [ReportController::class, 'index'])->name('reports');
        Route::get('/reports/export/csv', [ReportController::class, 'exportCsv'])->name('reports.export-csv');
        Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export-pdf');
        // Phase 6 sub-step 1.
        Route::get('/service-categories', [ServiceCategoryController::class, 'index'])->name('service-categories');
        Route::post('/service-categories', [ServiceCategoryController::class, 'store'])->name('service-categories.store');
        Route::put('/service-categories/{service_category}', [ServiceCategoryController::class, 'update'])->name('service-categories.update');
        Route::delete('/service-categories/{service_category}', [ServiceCategoryController::class, 'destroy'])->name('service-categories.destroy');
        Route::post('/service-categories/reorder', [ServiceCategoryController::class, 'reorder'])->name('service-categories.reorder');

        Route::get('/services', [ServiceController::class, 'index'])->name('services');
        Route::get('/services/export', [ServiceController::class, 'export'])->name('services.export');
        Route::get('/services/create', [ServiceController::class, 'create'])->name('services.create');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])->name('services.edit');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');

        // Phase 6 sub-step 4.
        Route::get('/services-import', [ServiceImportController::class, 'index'])->name('services.import');
        Route::get('/services-import/template', [ServiceImportController::class, 'template'])->name('services.import.template');
        Route::post('/services-import', [ServiceImportController::class, 'store'])->name('services.import.store');
        Route::get('/services-import/{import}', [ServiceImportController::class, 'show'])->name('services.import.show');
        Route::post('/services-import/{import}/commit', [ServiceImportController::class, 'commit'])->name('services.import.commit');
        Route::get('/services-import/{import}/error-report', [ServiceImportController::class, 'errorReport'])->name('services.import.error-report');

        // Phase 6 sub-step 3.
        Route::get('/deals', [DealController::class, 'index'])->name('deals');
        Route::get('/deals/create', [DealController::class, 'create'])->name('deals.create');
        Route::post('/deals', [DealController::class, 'store'])->name('deals.store');
        Route::get('/deals/{deal}/edit', [DealController::class, 'edit'])->name('deals.edit');
        Route::put('/deals/{deal}', [DealController::class, 'update'])->name('deals.update');
        Route::delete('/deals/{deal}', [DealController::class, 'destroy'])->name('deals.destroy');

        // Phase 6 sub-step 2.
        Route::get('/pricing', [ServicePriceController::class, 'index'])->name('pricing');
        Route::post('/pricing', [ServicePriceController::class, 'store'])->name('pricing.store');
        Route::put('/pricing/{service_price}', [ServicePriceController::class, 'update'])->name('pricing.update');
        Route::delete('/pricing/{service_price}', [ServicePriceController::class, 'destroy'])->name('pricing.destroy');
        Route::post('/pricing/bulk-adjust', [ServicePriceController::class, 'bulkAdjust'])->name('pricing.bulk-adjust');
        // Phase 11 sub-step 1.
        Route::get('/leads', [LeadController::class, 'index'])->name('leads');
        Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
        Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');
        Route::patch('/leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status');
        Route::patch('/leads/{lead}/assign', [LeadController::class, 'assign'])->name('leads.assign');
        Route::post('/leads/{lead}/notes', [LeadController::class, 'storeNote'])->name('leads.notes.store');
        // Phase 11 sub-step 2.
        Route::get('/messages', [MessageController::class, 'index'])->name('messages');
        Route::get('/messages/{message}', [MessageController::class, 'show'])->name('messages.show');
        Route::post('/messages/{message}/reply', [MessageController::class, 'reply'])->name('messages.reply');
        Route::patch('/messages/{message}/spam', [MessageController::class, 'toggleSpam'])->name('messages.spam');
        Route::post('/messages/bulk', [MessageController::class, 'bulkAction'])->name('messages.bulk');
        Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
        // Phase 11 sub-step 3.
        Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews');
        Route::patch('/reviews/{review}/approve', [ReviewController::class, 'approve'])->name('reviews.approve');
        Route::patch('/reviews/{review}/reject', [ReviewController::class, 'reject'])->name('reviews.reject');
        Route::post('/reviews/{review}/reply', [ReviewController::class, 'reply'])->name('reviews.reply');
        Route::post('/reviews/bulk', [ReviewController::class, 'bulkAction'])->name('reviews.bulk');
        Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
        // Phase 10 sub-step 2.
        Route::get('/blog', [BlogController::class, 'index'])->name('blog');
        Route::get('/blog/create', [BlogController::class, 'create'])->name('blog.create');
        Route::post('/blog', [BlogController::class, 'store'])->name('blog.store');
        Route::get('/blog/{post}/edit', [BlogController::class, 'edit'])->name('blog.edit');
        Route::put('/blog/{post}', [BlogController::class, 'update'])->name('blog.update');
        Route::delete('/blog/{post}', [BlogController::class, 'destroy'])->name('blog.destroy');
        Route::post('/blog-categories', [PostCategoryController::class, 'store'])->name('blog-categories.store');
        Route::delete('/blog-categories/{post_category}', [PostCategoryController::class, 'destroy'])->name('blog-categories.destroy');
        // Phase 10 sub-step 3.
        Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery');
        Route::get('/gallery/create', [GalleryController::class, 'create'])->name('gallery.create');
        Route::post('/gallery', [GalleryController::class, 'store'])->name('gallery.store');
        Route::get('/gallery/{gallery}/edit', [GalleryController::class, 'edit'])->name('gallery.edit');
        Route::put('/gallery/{gallery}', [GalleryController::class, 'update'])->name('gallery.update');
        Route::delete('/gallery/{gallery}', [GalleryController::class, 'destroy'])->name('gallery.destroy');
        Route::post('/gallery/{gallery}/images', [GalleryController::class, 'storeImages'])->name('gallery.images.store');
        Route::put('/gallery/{gallery}/images/{image}', [GalleryController::class, 'updateImage'])->name('gallery.images.update');
        Route::delete('/gallery/{gallery}/images/{image}', [GalleryController::class, 'destroyImage'])->name('gallery.images.destroy');
        Route::post('/gallery/{gallery}/images/reorder', [GalleryController::class, 'reorderImages'])->name('gallery.images.reorder');
        Route::post('/gallery/{gallery}/cover', [GalleryController::class, 'setCover'])->name('gallery.cover');
        Route::post('/gallery-categories', [GalleryCategoryController::class, 'store'])->name('gallery-categories.store');
        Route::put('/gallery-categories/{gallery_category}', [GalleryCategoryController::class, 'update'])->name('gallery-categories.update');
        Route::delete('/gallery-categories/{gallery_category}', [GalleryCategoryController::class, 'destroy'])->name('gallery-categories.destroy');
        Route::post('/gallery-categories/reorder', [GalleryCategoryController::class, 'reorder'])->name('gallery-categories.reorder');
        // Phase 10 sub-step 1.
        Route::get('/slider', [HeroSliderController::class, 'index'])->name('slider');
        Route::get('/slider/create', [HeroSliderController::class, 'create'])->name('slider.create');
        Route::post('/slider', [HeroSliderController::class, 'store'])->name('slider.store');
        Route::get('/slider/{slide}/edit', [HeroSliderController::class, 'edit'])->name('slider.edit');
        Route::put('/slider/{slide}', [HeroSliderController::class, 'update'])->name('slider.update');
        Route::delete('/slider/{slide}', [HeroSliderController::class, 'destroy'])->name('slider.destroy');
        Route::post('/slider/reorder', [HeroSliderController::class, 'reorder'])->name('slider.reorder');
        // Phase 11 sub-step 4.
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::patch('/customers/{customer}/notes', [CustomerController::class, 'updateNotes'])->name('customers.notes');
        Route::get('/customers/{customer}/export', [CustomerController::class, 'exportData'])->name('customers.export');
        Route::get('/reminders', fn () => Inertia::render('Admin/Reminders'))->name('reminders');
        // Phase 10 sub-step 4.
        Route::get('/settings', [SettingController::class, 'index'])->name('settings');
        Route::put('/settings/hours', [SettingController::class, 'updateHours'])->name('settings.hours');
        Route::post('/settings/business/logo', [SettingController::class, 'uploadLogo'])->name('settings.business.logo');
        Route::post('/settings/business/favicon', [SettingController::class, 'uploadFavicon'])->name('settings.business.favicon');
        Route::put('/settings/{group}', [SettingController::class, 'updateGroup'])->name('settings.update');

        // Phase 10 sub-step 5.
        Route::get('/media', [MediaLibraryController::class, 'index'])->name('media');
        Route::post('/media', [MediaLibraryController::class, 'store'])->name('media.store');
        Route::delete('/media/{media_asset}', [MediaLibraryController::class, 'destroy'])->name('media.destroy');

        // Phase 5 sub-step 2: the first real (non-placeholder) admin route — backed by a real
        // controller that calls ->authorize() against the Policy layer built in Phase 4, and this
        // sub-step's proof that DataTable works against real server-side pagination/sort/search.
        Route::get('/users', [UserController::class, 'index'])->name('users');

        // Phase 5 sub-step 4.
        Route::post('/users/invite', [UserController::class, 'invite'])->name('users.invite');
        Route::put('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.update-role');
        Route::post('/users/{user}/force-2fa', [UserController::class, 'forceTwoFactor'])->name('users.force-2fa');
        Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{user}/unsuspend', [UserController::class, 'unsuspend'])->name('users.unsuspend');
        Route::post('/users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('users.impersonate');
        Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');

        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log');
        Route::get('/notification-logs', [NotificationLogController::class, 'index'])->name('notification-logs');
    });
