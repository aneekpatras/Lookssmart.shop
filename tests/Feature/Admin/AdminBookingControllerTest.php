<?php

use App\Models\Booking;
use App\Models\BookingStatusLog;
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

it('requires bookings.view or bookings.manage — bookings.view_own alone is not enough', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff'); // only bookings.view_own per the seeder

    $this->actingAs($staff)->get('/admin/bookings')->assertForbidden();
});

it('lists bookings and filters by status', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    Booking::factory()->create(['status' => 'pending', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(45)]);
    Booking::factory()->create(['status' => 'completed', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addMinutes(45)]);

    $response = $this->actingAs($admin)->get('/admin/bookings?status=pending');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Bookings/Index')
        ->has('bookings.data', 1)
        ->where('bookings.data.0.status', 'pending'));
});

it('searches bookings by code', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $target = Booking::factory()->create(['code' => 'LS-FINDME', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(45)]);
    Booking::factory()->create(['code' => 'LS-OTHER', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addMinutes(45)]);

    $response = $this->actingAs($admin)->get('/admin/bookings?search=FINDME');

    $response->assertInertia(fn ($page) => $page
        ->has('bookings.data', 1)
        ->where('bookings.data.0.id', $target->id));
});

it('shows a booking detail with items, payments, and status history', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $booking = Booking::factory()->create(['status' => 'confirmed', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(45)]);

    $response = $this->actingAs($admin)->get("/admin/bookings/{$booking->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Bookings/Show')
        ->where('booking.id', $booking->id)
        ->where('booking.status', 'confirmed'));
});

it('transitions a booking through a valid status change and logs it', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $booking = Booking::factory()->create(['status' => 'pending', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(45)]);

    $response = $this->actingAs($admin)->patch("/admin/bookings/{$booking->id}/status", ['status' => 'confirmed']);

    $response->assertRedirect();
    expect($booking->fresh()->status)->toBe('confirmed');
    expect(BookingStatusLog::where('booking_id', $booking->id)->where('to_status', 'confirmed')->exists())->toBeTrue();
});

it('rejects an illegal status transition as a validation error, not a crash', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $booking = Booking::factory()->create(['status' => 'pending', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(45)]);

    $response = $this->actingAs($admin)->patch("/admin/bookings/{$booking->id}/status", ['status' => 'completed']);

    $response->assertSessionHasErrors('status');
    expect($booking->fresh()->status)->toBe('pending');
});

it('lets an admin cancel a booking starting within the policy window, bypassing the self-service restriction', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    // Starts in 1 hour — well inside the default 24h self-service cancellation window that would
    // reject a customer's own cancel attempt (CancelBookingActionTest covers that customer-facing
    // rejection); an admin acting directly must not be bound by it.
    $booking = Booking::factory()->create(['status' => 'confirmed', 'starts_at' => now()->addHour(), 'ends_at' => now()->addHour()->addMinutes(45)]);

    $response = $this->actingAs($admin)->patch("/admin/bookings/{$booking->id}/status", ['status' => 'cancelled', 'reason' => 'Staff unavailable']);

    $response->assertRedirect();
    expect($booking->fresh()->status)->toBe('cancelled');
    expect($booking->fresh()->cancellation_reason)->toBe('Staff unavailable');
});

/**
 * Ad hoc task 37: a real user report — a booking just placed for a future date wasn't showing at
 * the top of the admin list, because the list was ordered by the appointment's own `starts_at`
 * (soonest upcoming visit first), not by when the booking was actually created. A booking made
 * moments ago for next month would sit below one made last week for tomorrow — the opposite of
 * what "just came in" should look like to staff monitoring incoming activity.
 */
it('defaults to newest-created-first, not soonest-appointment-first', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $olderCreatedFarFutureAppointment = Booking::factory()->create([
        'created_at' => now()->subWeek(),
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addMinutes(45),
    ]);
    $justCreatedNearAppointment = Booking::factory()->create([
        'created_at' => now(),
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addMinutes(45),
    ]);

    $response = $this->actingAs($admin)->get('/admin/bookings');

    $response->assertInertia(fn ($page) => $page
        ->where('filters.sort', 'created_at')
        ->where('filters.direction', 'desc')
        ->where('bookings.data.0.id', $justCreatedNearAppointment->id)
        ->where('bookings.data.1.id', $olderCreatedFarFutureAppointment->id));
});

it('lets an admin delete a booking, soft-deleting it out of the active list', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $booking = Booking::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(45)]);

    $response = $this->actingAs($admin)->delete("/admin/bookings/{$booking->id}");

    $response->assertRedirect();
    $this->assertSoftDeleted($booking);
    expect(Booking::withTrashed()->find($booking->id)->trashed())->toBeTrue();

    $listResponse = $this->actingAs($admin)->get('/admin/bookings');
    $listResponse->assertInertia(fn ($page) => $page->has('bookings.data', 0));
});

it('does not let a staff member with only bookings.view_own delete a booking they do not own', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff');
    $booking = Booking::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(45)]);

    $this->actingAs($staff)->delete("/admin/bookings/{$booking->id}")->assertForbidden();
    expect($booking->fresh())->not->toBeNull();
});
