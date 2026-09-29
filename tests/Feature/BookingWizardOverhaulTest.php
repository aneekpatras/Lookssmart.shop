<?php

use App\Models\Booking;
use App\Models\BusinessHour;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffWorkingHour;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the real, backend-observable parts of the "single-step booking overhaul" task: the new
 * `notes` field genuinely persists through a real booking, and every booking a customer creates or
 * cancels is immediately visible to the admin bookings list — proving "admin sync" is real rather
 * than assumed, since `AdminBookingController::index()` has no caching layer to invalidate in the
 * first place (a plain fresh Eloquent query every visit).
 */
function bookableSlot(): array
{
    // +3 days rather than +1: guarantees >24h away regardless of what time-of-day the test suite
    // actually runs at, which matters for the cancellation-window assertions below.
    $day = CarbonImmutable::now('UTC')->addDays(3);
    BusinessHour::firstOrCreate(
        ['weekday' => $day->dayOfWeek],
        ['open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_closed' => false],
    );

    $staff = Staff::factory()->create(['is_active' => true]);
    StaffWorkingHour::create([
        'staff_id' => $staff->id, 'weekday' => $day->dayOfWeek,
        'start_time' => '09:00:00', 'end_time' => '18:00:00',
    ]);

    $category = ServiceCategory::factory()->create(['name' => 'Hair', 'is_active' => true]);
    $service = Service::factory()->create([
        'service_category_id' => $category->id, 'name' => 'Signature Cut',
        'duration_min' => 30, 'buffer_min' => 0, 'base_price' => 45, 'is_active' => true,
    ]);
    $staff->services()->attach($service->id);

    return ['staff' => $staff, 'service' => $service, 'startsAt' => $day->setTime(9, 0)];
}

beforeEach(function () {
    Setting::firstOrCreate(['key' => 'booking.tax_rate'], ['value' => 0, 'group' => 'booking']);
    Setting::firstOrCreate(['key' => 'booking.slot_minutes'], ['value' => 15, 'group' => 'booking']);
    Setting::firstOrCreate(['key' => 'booking.min_lead_minutes'], ['value' => 0, 'group' => 'booking']);
    Setting::firstOrCreate(['key' => 'booking.max_advance_days'], ['value' => 60, 'group' => 'booking']);
    Setting::firstOrCreate(['key' => 'booking.hold_minutes'], ['value' => 5, 'group' => 'booking']);
    Setting::firstOrCreate(['key' => 'booking.cancellation_window_hours'], ['value' => 24, 'group' => 'booking']);
});

it('persists the customer-supplied notes onto the real booking row', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    ['staff' => $staff, 'service' => $service, 'startsAt' => $startsAt] = bookableSlot();
    $customer = User::factory()->create(['email_verified_at' => now()]);
    $customer->assignRole('customer');

    $quote = $this->actingAs($customer)->postJson('/api/booking/quote', [
        'service_ids' => [$service->id],
    ])->assertOk()->json();

    $store = $this->actingAs($customer)->postJson('/api/booking', [
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'starts_at' => $startsAt->toIso8601String(),
        'timezone' => 'UTC',
        'notes' => 'Please use fragrance-free products — sensitive skin.',
        'quote' => $quote,
    ])->assertCreated();

    $booking = Booking::where('code', $store->json('code'))->first();
    expect($booking->notes)->toBe('Please use fragrance-free products — sensitive skin.');
});

it('folds the customer-supplied subject onto the notes field, since bookings has no dedicated subject column', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    ['staff' => $staff, 'service' => $service, 'startsAt' => $startsAt] = bookableSlot();
    $customer = User::factory()->create(['email_verified_at' => now()]);
    $customer->assignRole('customer');

    $quote = $this->actingAs($customer)->postJson('/api/booking/quote', [
        'service_ids' => [$service->id],
    ])->assertOk()->json();

    $store = $this->actingAs($customer)->postJson('/api/booking', [
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'starts_at' => $startsAt->toIso8601String(),
        'timezone' => 'UTC',
        'subject' => 'First-time visit question',
        'notes' => 'I have a nut allergy.',
        'quote' => $quote,
    ])->assertCreated();

    $booking = Booking::where('code', $store->json('code'))->first();
    expect($booking->notes)->toBe("Subject: First-time visit question\n\nI have a nut allergy.");
});

it('folds only the subject onto notes when no separate notes text was given', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    ['staff' => $staff, 'service' => $service, 'startsAt' => $startsAt] = bookableSlot();
    $customer = User::factory()->create(['email_verified_at' => now()]);
    $customer->assignRole('customer');

    $quote = $this->actingAs($customer)->postJson('/api/booking/quote', [
        'service_ids' => [$service->id],
    ])->assertOk()->json();

    $store = $this->actingAs($customer)->postJson('/api/booking', [
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'starts_at' => $startsAt->toIso8601String(),
        'timezone' => 'UTC',
        'subject' => 'Just a subject, no notes',
        'quote' => $quote,
    ])->assertCreated();

    $booking = Booking::where('code', $store->json('code'))->first();
    expect($booking->notes)->toBe('Subject: Just a subject, no notes');
});

it('creates a booking with no notes at all when the field is omitted, without error', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    ['staff' => $staff, 'service' => $service, 'startsAt' => $startsAt] = bookableSlot();
    $customer = User::factory()->create(['email_verified_at' => now()]);
    $customer->assignRole('customer');

    $quote = $this->actingAs($customer)->postJson('/api/booking/quote', [
        'service_ids' => [$service->id],
    ])->assertOk()->json();

    $store = $this->actingAs($customer)->postJson('/api/booking', [
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'starts_at' => $startsAt->toIso8601String(),
        'timezone' => 'UTC',
        'quote' => $quote,
    ])->assertCreated();

    expect(Booking::where('code', $store->json('code'))->first()->notes)->toBeNull();
});

it('reflects a customer-created booking on the admin bookings list immediately, with no separate sync step', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    ['staff' => $staff, 'service' => $service, 'startsAt' => $startsAt] = bookableSlot();
    $customer = User::factory()->create(['email_verified_at' => now()]);
    $customer->assignRole('customer');
    $admin = User::factory()->create([
        'email_verified_at' => now(), 'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $admin->assignRole('admin');

    $quote = $this->actingAs($customer)->postJson('/api/booking/quote', [
        'service_ids' => [$service->id],
    ])->assertOk()->json();

    $store = $this->actingAs($customer)->postJson('/api/booking', [
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'starts_at' => $startsAt->toIso8601String(),
        'timezone' => 'UTC',
        'quote' => $quote,
    ])->assertCreated();
    $code = $store->json('code');

    // A genuinely separate request from a different (admin) user session — no shared process state,
    // no cache to warm — proving the admin list is reading the real, just-committed row.
    $this->actingAs($admin)->get('/admin/bookings')->assertOk()->assertInertia(fn ($page) => $page
        ->where('bookings.data.0.code', $code)
        ->where('bookings.data.0.status', 'pending'));

    // Cancelling as the customer is reflected on the admin list immediately too, still with no
    // manual sync/refresh step beyond the admin's own next page visit.
    $this->actingAs($customer)->postJson("/api/booking/{$code}/cancel", ['reason' => 'Change of plans'])
        ->assertOk();

    $this->actingAs($admin)->get('/admin/bookings?status=cancelled')->assertInertia(fn ($page) => $page
        ->where('bookings.data.0.code', $code)
        ->where('bookings.data.0.status', 'cancelled'));
});
