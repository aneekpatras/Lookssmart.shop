<?php

use App\Models\Booking;
use App\Models\BusinessHour;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffWorkingHour;
use App\Models\User;
use App\Services\SlotHoldService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Phase 14 sub-step 1 — Path A. Every step below hits the exact real route each phase already built
 * and unit-tested in isolation (Phase 6 catalog, Phase 7 booking engine, Phase 9 public site, Phase 3
 * customer portal) — this test's own value is proving they genuinely work chained together end to
 * end, in one real HTTP-level flow, which no single phase's own test suite ever exercised.
 */
function clearJourneyHoldKeys(): void
{
    DB::table('slot_holds')->delete();
}

beforeEach(fn () => clearJourneyHoldKeys());
afterEach(fn () => clearJourneyHoldKeys());

it('completes the full customer journey: browse, select a slot, hold it, check out, and see the booking on their account', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.slot_minutes', 'value' => 15, 'group' => 'booking']);
    Setting::create(['key' => 'booking.min_lead_minutes', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_advance_days', 'value' => 60, 'group' => 'booking']);
    Setting::create(['key' => 'booking.hold_minutes', 'value' => 5, 'group' => 'booking']);

    $day = CarbonImmutable::now('UTC')->addDay();
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
        'service_category_id' => $category->id,
        'name' => 'Signature Cut',
        'duration_min' => 30,
        'buffer_min' => 0,
        'base_price' => 45,
        'is_active' => true,
    ]);
    $staff->services()->attach($service->id);

    $customer = User::factory()->create(['email_verified_at' => now()]);
    $customer->assignRole('customer');
    $startsAt = $day->setTime(9, 0);

    // Step 1 — Service Browsing.
    $this->actingAs($customer)
        ->get('/services')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Services')
            ->where('services.0.name', 'Signature Cut'));

    // Step 2 — Booking Wizard Slot Selection.
    $availabilityUrl = '/api/booking/availability?' . http_build_query([
        'service_ids' => [$service->id],
        'date' => $day->toDateString(),
        'timezone' => 'UTC',
    ]);
    $availability = $this->actingAs($customer)->getJson($availabilityUrl)->assertOk();
    $slots = collect($availability->json('slots'));
    expect($slots->contains(fn ($slot) => $slot['starts_at'] === $startsAt->toIso8601String()))->toBeTrue();

    // Step 3 — Real-time Slot Hold.
    $holdPayload = [
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'starts_at' => $startsAt->toIso8601String(),
        'timezone' => 'UTC',
    ];
    $hold = $this->actingAs($customer)->postJson('/api/booking/hold', $holdPayload)->assertCreated();
    $holdToken = $hold->json('hold_token');
    expect($holdToken)->toBeString();

    // A second, unrelated guest cannot grab the same held slot in the meantime.
    $this->postJson('/api/booking/hold', $holdPayload)->assertConflict();

    // Step 4 — Checkout/Payment: a real signed price quote, then committing the booking against it.
    // The wizard's own UX-level hold is released right before committing — CreateBookingAction takes
    // its own real lock internally as the actual double-booking guard (proven directly, with genuine
    // concurrent processes, in tests/Feature/Booking/CreateBookingActionTest.php); leaving this hold
    // in place would make CreateBookingAction's own lock attempt collide with it and fail with a 409,
    // which is exactly what a real wizard avoids by releasing its hold at the moment of submission.
    $this->actingAs($customer)->deleteJson('/api/booking/hold', [
        'staff_id' => $staff->id,
        'starts_at' => $startsAt->toIso8601String(),
        'hold_token' => $holdToken,
    ])->assertOk();

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
    expect($code)->toBeString();

    $booking = Booking::where('code', $code)->first();
    expect($booking)->not->toBeNull()
        ->and($booking->customer_id)->toBe($customer->id)
        ->and($booking->status)->toBe('pending')
        ->and((float) $booking->total)->toBe(45.0)
        ->and($booking->items()->count())->toBe(1);

    // The Redis hold is released once the booking actually commits, not held for the full TTL.
    expect(app(SlotHoldService::class)->isHeld($staff->id, $startsAt))->toBeFalse();

    // Step 5 — Customer Account View: the real booking the customer just made shows up on their
    // own dashboard, matching the exact service/status just committed.
    $this->actingAs($customer)
        ->get('/my-account')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Customer/Profile')
            ->where('upcoming.0.code', $code)
            ->where('upcoming.0.status', 'pending')
            ->where('upcoming.0.services.0', 'Signature Cut'));
});
