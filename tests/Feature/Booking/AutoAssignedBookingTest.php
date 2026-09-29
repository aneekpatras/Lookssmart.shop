<?php

use App\Models\Booking;
use App\Models\BusinessHour;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffWorkingHour;
use App\Services\SlotHoldService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Ad hoc task 30: the public booking API's real end-to-end flow through `/api/booking/availability`,
 * `/api/booking/hold` and `/api/booking` once the customer no longer picks a specific staff
 * member — the salon-wide capacity grid decides what's bookable, and a real staff member is resolved
 * and locked entirely server-side, invisibly, exactly as `Book.tsx` (which never sends `staff_id`
 * anymore) actually calls these endpoints.
 */
function clearAutoAssignHoldKeys(): void
{
    DB::table('slot_holds')->delete();
}

beforeEach(fn () => clearAutoAssignHoldKeys());
afterEach(fn () => clearAutoAssignHoldKeys());

function seedAutoAssignSettings(): void
{
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.slot_minutes', 'value' => 60, 'group' => 'booking']);
    Setting::create(['key' => 'booking.min_lead_minutes', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_advance_days', 'value' => 60, 'group' => 'booking']);
    Setting::create(['key' => 'booking.hold_minutes', 'value' => 5, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_bookings_per_slot', 'value' => 2, 'group' => 'booking']);
}

/** @return array{0: Collection<int, Staff>, 1: Service} */
function makeStaffPoolAndService(int $weekday, int $count): array
{
    BusinessHour::firstOrCreate(['weekday' => $weekday], ['open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_closed' => false]);

    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['service_category_id' => $category->id, 'duration_min' => 30, 'buffer_min' => 0, 'base_price' => 50]);

    $staff = Staff::factory()->count($count)->create(['is_active' => true])->each(function (Staff $staff) use ($weekday, $service) {
        StaffWorkingHour::create(['staff_id' => $staff->id, 'weekday' => $weekday, 'start_time' => '09:00:00', 'end_time' => '18:00:00']);
        $staff->services()->attach($service->id);
    });

    return [$staff, $service];
}

it('the availability endpoint no longer accepts or requires staff_id and returns is_available flags', function () {
    seedAutoAssignSettings();
    $day = CarbonImmutable::now('UTC')->addDay();
    [, $service] = makeStaffPoolAndService($day->dayOfWeek, 1);

    $response = $this->getJson('/api/booking/availability?' . http_build_query([
        'service_ids' => [$service->id],
        'date' => $day->toDateString(),
        'timezone' => 'UTC',
    ]))->assertOk();

    $slots = $response->json('slots');
    expect($slots)->not->toBeEmpty();
    foreach ($slots as $slot) {
        expect($slot)->toHaveKeys(['starts_at', 'ends_at', 'is_available'])
            ->and($slot)->not->toHaveKey('staff_id');
    }
});

it('holding a slot with no staff_id auto-resolves and locks a real staff member', function () {
    seedAutoAssignSettings();
    $day = CarbonImmutable::now('UTC')->addDay();
    [$staffPool, $service] = makeStaffPoolAndService($day->dayOfWeek, 1);
    $startsAt = $day->setTime(9, 0);

    $response = $this->postJson('/api/booking/hold', [
        'service_ids' => [$service->id],
        'starts_at' => $startsAt->toIso8601String(),
        'timezone' => 'UTC',
    ])->assertCreated();

    $resolvedStaffId = $response->json('staff_id');
    expect($resolvedStaffId)->toBe($staffPool->first()->id)
        ->and(app(SlotHoldService::class)->isHeld($resolvedStaffId, $startsAt))->toBeTrue();
});

it('creating a booking with no staff_id auto-resolves a real staff member and writes it onto the booking', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    seedAutoAssignSettings();
    $day = CarbonImmutable::now('UTC')->addDay();
    [$staffPool, $service] = makeStaffPoolAndService($day->dayOfWeek, 1);
    $startsAt = $day->setTime(9, 0);

    $quote = $this->postJson('/api/booking/quote', ['service_ids' => [$service->id]])->assertOk()->json();

    $response = $this->postJson('/api/booking', [
        'service_ids' => [$service->id],
        'starts_at' => $startsAt->toIso8601String(),
        'timezone' => 'UTC',
        'guest_name' => 'Auto Assign Guest',
        'guest_email' => 'auto-assign@example.com',
        'quote' => $quote,
    ])->assertCreated();

    $booking = Booking::where('code', $response->json('code'))->firstOrFail();
    expect($booking->staff_id)->toBe($staffPool->first()->id);
});

it('lets bookings up to the salon-wide capacity cap succeed across different auto-assigned staff, then refuses the next one', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    seedAutoAssignSettings(); // cap = 2
    $day = CarbonImmutable::now('UTC')->addDay();
    [$staffPool, $service] = makeStaffPoolAndService($day->dayOfWeek, 3);
    $startsAt = $day->setTime(9, 0);

    $quote = $this->postJson('/api/booking/quote', ['service_ids' => [$service->id]])->assertOk()->json();

    $results = [];
    for ($i = 0; $i < 3; $i++) {
        $response = $this->postJson('/api/booking', [
            'service_ids' => [$service->id],
            'starts_at' => $startsAt->toIso8601String(),
            'timezone' => 'UTC',
            'guest_name' => "Capacity Guest {$i}",
            'guest_email' => "capacity{$i}@example.com",
            'quote' => $quote,
        ]);
        $results[] = $response->status();
    }

    // The first 2 land on the 2 distinct staff who both genuinely qualify for this exact time; the
    // 3rd is refused — not because that 3rd staff member is personally busy, but because the SALON
    // slot itself has reached its configured cap (the whole point of ad hoc task 30's capacity model).
    expect($results)->toBe([201, 201, 409])
        ->and(Booking::where('starts_at', $startsAt)->count())->toBe(2)
        ->and(Booking::where('starts_at', $startsAt)->pluck('staff_id')->unique()->count())->toBe(2);
});

/**
 * Ad hoc task 41: reverses task 36's own blocking behavior, per an explicit later instruction —
 * "customers should be able to book any combination of services without being restricted by staff
 * availability or staff-service mapping." With plenty of salon-wide capacity free, selecting two
 * services that no single staff member offers together now SUCCEEDS with a `null` `staff_id` —
 * the salon-wide capacity seat (already claimed regardless of staff) remains the real, authoritative
 * guard against overbooking the slot; there is simply no staff-service-coverage requirement left to
 * block it. A combination a real staff member DOES cover is unaffected (see the sibling tests below)
 * — this only ever falls back to `null` when resolution genuinely finds zero candidates.
 */
it('holds and books a service combination that no single staff member offers together, falling back to a staff-less booking', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    seedAutoAssignSettings();
    $day = CarbonImmutable::now('UTC')->addDay();
    BusinessHour::firstOrCreate(['weekday' => $day->dayOfWeek], ['open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_closed' => false]);

    $category = ServiceCategory::factory()->create();
    $serviceA = Service::factory()->create(['service_category_id' => $category->id, 'duration_min' => 30, 'buffer_min' => 0, 'base_price' => 50]);
    $serviceB = Service::factory()->create(['service_category_id' => $category->id, 'duration_min' => 30, 'buffer_min' => 0, 'base_price' => 50]);

    $staffA = Staff::factory()->create(['is_active' => true]);
    StaffWorkingHour::create(['staff_id' => $staffA->id, 'weekday' => $day->dayOfWeek, 'start_time' => '09:00:00', 'end_time' => '18:00:00']);
    $staffA->services()->attach($serviceA->id);

    $staffB = Staff::factory()->create(['is_active' => true]);
    StaffWorkingHour::create(['staff_id' => $staffB->id, 'weekday' => $day->dayOfWeek, 'start_time' => '09:00:00', 'end_time' => '18:00:00']);
    $staffB->services()->attach($serviceB->id);

    $startsAt = $day->setTime(9, 0);

    $this->postJson('/api/booking/hold', [
        'service_ids' => [$serviceA->id, $serviceB->id],
        'starts_at' => $startsAt->toIso8601String(),
        'timezone' => 'UTC',
    ])->assertCreated()->assertJson(['staff_id' => null]);

    $quote = $this->postJson('/api/booking/quote', ['service_ids' => [$serviceA->id, $serviceB->id]])->assertOk()->json();

    $this->postJson('/api/booking', [
        'service_ids' => [$serviceA->id, $serviceB->id],
        'starts_at' => $startsAt->toIso8601String(),
        'timezone' => 'UTC',
        'guest_name' => 'No Overlap Guest',
        'guest_email' => 'no-overlap@example.com',
        'quote' => $quote,
    ])->assertCreated();

    $booking = Booking::where('starts_at', $startsAt)->first();
    expect($booking)->not->toBeNull()
        ->and($booking->staff_id)->toBeNull();
});
