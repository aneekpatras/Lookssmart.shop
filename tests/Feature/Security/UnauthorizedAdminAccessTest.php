<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects unauthenticated visitors away from admin pages', function () {
    $this->get('/admin')->assertRedirect('/login');
    $this->get('/admin/settings')->assertRedirect('/login');
});

it('lets an admin without 2FA reach the dashboard directly — 2FA is no longer mandatory', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
});

it('blocks a plain customer from admin page shells with a 403', function () {
    // Fixed 2026-08-26 (audit finding, High severity): routes/admin.php now carries
    // 'role:super-admin|admin|receptionist|staff' (spatie/laravel-permission's RoleMiddleware) on
    // top of auth+verified+2FA. This is a stopgap ROUTE-level check, not per-action ->authorize()
    // calls against the Policy layer — that granular wiring is still Phase 5/6's job. A customer
    // (or a guest) reaching /admin now gets a real 403, not the page shell.
    $this->seed(RolesAndPermissionsSeeder::class);

    $customer = User::factory()->create(['email_verified_at' => now()]);
    $customer->assignRole('customer');

    $response = $this->actingAs($customer)->get('/admin');

    $response->assertForbidden();
});

it('lets a staff member (own schedule + own bookings per Brief §2) reach the admin shell', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $staff->assignRole('staff');

    $response = $this->actingAs($staff)->get('/admin');

    $response->assertOk();
});

it('blocks an unauthenticated guest from admin page shells with a redirect, not a 403', function () {
    // Unauthenticated requests never reach the role check at all — `auth` middleware runs first and
    // redirects to /login. Restated here explicitly (also covered by the first test in this file)
    // so the 403-vs-redirect distinction between "logged in, wrong role" and "not logged in" is clear.
    $this->get('/admin')->assertRedirect('/login');
});
