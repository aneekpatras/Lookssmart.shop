<?php

use App\Models\Booking;
use App\Models\CustomerProfile;
use App\Models\Review;
use App\Models\Staff;
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

it('requires crm.manage to view the customer directory', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff'); // no crm.manage

    $this->actingAs($staff)->get('/admin/customers')->assertForbidden();
});

it('computes LTV from real completed bookings, not the stale total_spent column', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $customer = User::factory()->create();
    CustomerProfile::factory()->create(['user_id' => $customer->id, 'total_spent' => 999999]); // deliberately wrong, must be ignored
    $staffMember = Staff::factory()->create();

    Booking::factory()->create(['customer_id' => $customer->id, 'staff_id' => $staffMember->id, 'status' => 'completed', 'total' => 50]);
    Booking::factory()->create(['customer_id' => $customer->id, 'staff_id' => $staffMember->id, 'status' => 'completed', 'total' => 75]);
    Booking::factory()->create(['customer_id' => $customer->id, 'staff_id' => $staffMember->id, 'status' => 'cancelled', 'total' => 1000]);

    $response = $this->actingAs($admin)->get('/admin/customers');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/CRM/Customers/Index')
        ->where('customers', fn ($customers) => collect($customers)->firstWhere('id', $customer->id)['ltv'] === '125.00'));
});

it('shows a real Customer 360 profile with aggregated appointment and no-show counts', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $customer = User::factory()->create();
    CustomerProfile::factory()->create(['user_id' => $customer->id]);
    $staffMember = Staff::factory()->create();

    Booking::factory()->create(['customer_id' => $customer->id, 'staff_id' => $staffMember->id, 'status' => 'completed', 'total' => 100]);
    Booking::factory()->create(['customer_id' => $customer->id, 'staff_id' => $staffMember->id, 'status' => 'no_show', 'total' => 40]);
    Booking::factory()->create(['customer_id' => $customer->id, 'staff_id' => $staffMember->id, 'status' => 'cancelled', 'total' => 60]);
    Review::factory()->create(['customer_id' => $customer->id, 'rating' => 5, 'status' => 'approved']);

    $response = $this->actingAs($admin)->get("/admin/customers/{$customer->id}");

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/CRM/Customers/Show')
        ->where('stats.ltv', '100.00')
        ->where('stats.total_appointments', 3)
        ->where('stats.completed_appointments', 1)
        ->where('stats.no_show_count', 1)
        ->where('stats.cancelled_count', 1)
        ->where('stats.average_rating', 5));
});

it('includes appointments and reviews in the activity timeline', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $customer = User::factory()->create();
    $staffMember = Staff::factory()->create();
    Booking::factory()->create(['customer_id' => $customer->id, 'staff_id' => $staffMember->id, 'status' => 'completed']);
    Review::factory()->create(['customer_id' => $customer->id, 'status' => 'approved']);

    $response = $this->actingAs($admin)->get("/admin/customers/{$customer->id}");

    $response->assertInertia(fn ($page) => $page
        ->where('timeline', fn ($timeline) => collect($timeline)->pluck('type')->contains('appointment')
            && collect($timeline)->pluck('type')->contains('review')));
});

it('updates customer notes and flags, and creates a profile if one does not exist yet', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $customer = User::factory()->create(); // deliberately no CustomerProfile yet

    expect(CustomerProfile::where('user_id', $customer->id)->exists())->toBeFalse();

    $response = $this->actingAs($admin)->patch("/admin/customers/{$customer->id}/notes", [
        'notes' => 'Prefers morning appointments, allergic to certain products.',
        'tags' => ['VIP', 'High No-Show Risk'],
        'is_blacklisted' => false,
    ]);

    $response->assertRedirect();
    $profile = CustomerProfile::where('user_id', $customer->id)->first();
    expect($profile)->not->toBeNull()
        ->and($profile->notes)->toBe('Prefers morning appointments, allergic to certain products.')
        ->and($profile->tags)->toBe(['VIP', 'High No-Show Risk']);
});

it('exports a customer data file with real profile and booking data', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $customer = User::factory()->create();
    CustomerProfile::factory()->create(['user_id' => $customer->id]);
    $staffMember = Staff::factory()->create();
    Booking::factory()->create(['customer_id' => $customer->id, 'staff_id' => $staffMember->id, 'status' => 'completed']);

    $response = $this->actingAs($admin)->get("/admin/customers/{$customer->id}/export");

    $response->assertOk()
        ->assertHeader('Content-Disposition', "attachment; filename=customer-{$customer->id}-export.json")
        ->assertJsonPath('customer.id', $customer->id)
        ->assertJsonCount(1, 'bookings');
});

it('filters the customer directory by search term', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $matching = User::factory()->create(['name' => 'Jamie Rivera', 'email' => 'jamie@example.com']);
    CustomerProfile::factory()->create(['user_id' => $matching->id]);
    $other = User::factory()->create(['name' => 'Alex Chen']);
    CustomerProfile::factory()->create(['user_id' => $other->id]);

    $response = $this->actingAs($admin)->get('/admin/customers?search=Jamie');

    $response->assertInertia(fn ($page) => $page
        ->where('customers', fn ($customers) => count($customers) === 1 && $customers[0]['id'] === $matching->id));
});
