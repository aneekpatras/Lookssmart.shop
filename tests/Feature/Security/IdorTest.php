<?php

use App\Models\Booking;
use App\Models\CustomerProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * Brief §5 item 5 (IDOR scoping). No real admin controller takes a resource ID from the URL yet
 * (routes/admin.php is still Phase 1 placeholder shells — Phase 5+ builds real CRUD), so this tests
 * the actual authorization layer directly (Policy → Gate, against real persisted rows, not mocks) —
 * that's what would stop an IDOR at the controller level once one exists, since every future
 * `$this->authorize('view', $booking)` call resolves through exactly this mechanism.
 */
it('prevents one customer from viewing another customer\'s booking', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $owner = User::factory()->create();
    $owner->assignRole('customer');

    $intruder = User::factory()->create();
    $intruder->assignRole('customer');

    $booking = Booking::factory()->create(['customer_id' => $owner->id]);

    expect(Gate::forUser($owner)->allows('view', $booking))->toBeTrue()
        ->and(Gate::forUser($intruder)->allows('view', $booking))->toBeFalse();
});

it('prevents one customer from viewing another customer\'s profile', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $owner = User::factory()->create();
    $owner->assignRole('customer');
    $profile = CustomerProfile::factory()->create(['user_id' => $owner->id]);

    $intruder = User::factory()->create();
    $intruder->assignRole('customer');

    expect(Gate::forUser($owner)->allows('view', $profile))->toBeTrue()
        ->and(Gate::forUser($intruder)->allows('view', $profile))->toBeFalse();
});

it('lets staff with bookings.manage view a booking that is not theirs', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $booking = Booking::factory()->create(['customer_id' => $customer->id]);

    $receptionist = User::factory()->create();
    $receptionist->assignRole('receptionist');

    expect(Gate::forUser($receptionist)->allows('view', $booking))->toBeTrue();
});
