<?php

use App\Models\Booking;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

$makeConfirmedUser = function (string $role): User {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
};

it('requires audit.view (super-admin only per Brief §3 module 18)', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin'); // admin does NOT have audit.view per the seeder

    $response = $this->actingAs($admin)->get('/admin/audit-log');

    $response->assertForbidden();
});

it('lets a super-admin view real logged activity', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = $makeConfirmedUser('super-admin');
    $booking = Booking::factory()->create();
    $booking->update(['status' => 'confirmed']);

    $response = $this->actingAs($superAdmin)->get('/admin/audit-log');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/AuditLog')
        ->has('activities.data'));
});
