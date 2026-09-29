<?php

use App\Models\Booking;
use App\Models\CustomerProfile;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows only the authenticated customer dashboard data', function () {
    $customer = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $staff = Staff::factory()->create();
    Booking::factory()->create(['customer_id' => $customer->id, 'staff_id' => $staff->id, 'code' => 'LS-MINE01']);
    Booking::factory()->create(['customer_id' => $other->id, 'staff_id' => $staff->id, 'code' => 'LS-OTHER1']);

    $this->actingAs($customer)->get('/my-account')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Customer/Profile')
        ->where('bookings.total', 1)
        ->where('bookings.data.0.code', 'LS-MINE01')
        ->where('profile.email_opt_out', false));
});

it('updates the authenticated customer phone and notification preferences', function () {
    $customer = User::factory()->create(['email_verified_at' => now()]);
    CustomerProfile::factory()->create(['user_id' => $customer->id]);

    $this->actingAs($customer)->put('/my-account/preferences', [
        'phone' => '+15551234567',
        'marketing_opt_in' => true,
        'email_opt_out' => false,
        'sms_opt_out' => true,
        'whatsapp_opt_out' => false,
    ])->assertRedirect();

    expect($customer->fresh()->phone)->toBe('+15551234567')
        ->and($customer->customerProfile->fresh()->marketing_opt_in)->toBeTrue()
        ->and($customer->customerProfile->fresh()->sms_opt_out)->toBeTrue();
});

it('shows only the authenticated customer\'s own bookings on the dedicated My Bookings page, newest first', function () {
    $customer = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $staff = Staff::factory()->create();

    Booking::factory()->create([
        'customer_id' => $customer->id, 'staff_id' => $staff->id,
        'code' => 'LS-OLDER01', 'starts_at' => now()->subDays(10),
    ]);
    Booking::factory()->create([
        'customer_id' => $customer->id, 'staff_id' => $staff->id,
        'code' => 'LS-NEWER01', 'starts_at' => now()->subDays(1),
    ]);
    Booking::factory()->create(['customer_id' => $other->id, 'staff_id' => $staff->id, 'code' => 'LS-OTHER01']);

    $this->actingAs($customer)->get('/my-bookings')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Customer/Bookings')
        ->has('bookings.data', 2)
        ->where('bookings.data.0.code', 'LS-NEWER01')
        ->where('bookings.data.1.code', 'LS-OLDER01')
        ->where('cancellationWindowHours', 24));
});

it('marks a booking cancellable and outside the fee window when it starts well beyond 24 hours away', function () {
    $customer = User::factory()->create(['email_verified_at' => now()]);
    $staff = Staff::factory()->create();
    Booking::factory()->create([
        'customer_id' => $customer->id, 'staff_id' => $staff->id, 'status' => 'confirmed',
        'starts_at' => now()->addDays(3),
    ]);

    $this->actingAs($customer)->get('/my-bookings')->assertInertia(fn ($page) => $page
        ->where('bookings.data.0.can_cancel', true)
        ->where('bookings.data.0.within_cancellation_fee_window', false));
});

it('flags the fee-warning window when a booking starts within the next 24 hours', function () {
    $customer = User::factory()->create(['email_verified_at' => now()]);
    $staff = Staff::factory()->create();
    Booking::factory()->create([
        'customer_id' => $customer->id, 'staff_id' => $staff->id, 'status' => 'confirmed',
        'starts_at' => now()->addHours(5),
    ]);

    $this->actingAs($customer)->get('/my-bookings')->assertInertia(fn ($page) => $page
        ->where('bookings.data.0.can_cancel', true)
        ->where('bookings.data.0.within_cancellation_fee_window', true));
});

it('never marks a completed or cancelled booking as cancellable, regardless of timing', function () {
    $customer = User::factory()->create(['email_verified_at' => now()]);
    $staff = Staff::factory()->create();
    Booking::factory()->create([
        'customer_id' => $customer->id, 'staff_id' => $staff->id, 'status' => 'completed',
        'starts_at' => now()->addDays(3),
    ]);

    $this->actingAs($customer)->get('/my-bookings')->assertInertia(fn ($page) => $page
        ->where('bookings.data.0.can_cancel', false));
});
