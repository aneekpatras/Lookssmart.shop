<?php

use App\Models\Booking;
use App\Models\BusinessHour;
use App\Models\SalonHoliday;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Models\StaffWorkingHour;
use App\Services\AvailabilityEngine;
use App\Services\SlotHoldService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Ad hoc task 30: `AvailabilityEngine::getSlots()` is now a fixed-width, salon-wide CAPACITY grid,
 * completely detached from individual staff schedules — `getSlots()` no longer takes a `$staffId`
 * argument and no longer enumerates staff at all; every returned entry carries `is_available` instead
 * of `staff_id`, and a full slot is still RETURNED (never hidden), matching the task's explicit rule.
 * The old per-staff resolution logic this file used to test directly through `getSlots()` still
 * exists, just moved to `resolveStaffCandidates()`/`isStaffFreeAt()` — internal, invisible helpers
 * `BookingController::hold()`/`store()` call AFTER a customer picks a capacity slot, covered by their
 * own tests below.
 */
function clearHoldKeys(): void
{
    DB::table('slot_holds')->delete();
}

beforeEach(fn () => clearHoldKeys());
afterEach(fn () => clearHoldKeys());

function seedBookingSettings(): void
{
    Setting::create(['key' => 'business.timezone', 'value' => 'UTC', 'group' => 'business']);
    Setting::create(['key' => 'booking.slot_minutes', 'value' => 60, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_advance_days', 'value' => 60, 'group' => 'booking']);
    Setting::create(['key' => 'booking.hold_minutes', 'value' => 5, 'group' => 'booking']);
    Setting::create(['key' => 'booking.min_lead_minutes', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_bookings_per_slot', 'value' => 3, 'group' => 'booking']);
}

function makeBookableService(): Service
{
    $category = ServiceCategory::factory()->create();

    return Service::factory()->create(['service_category_id' => $category->id, 'duration_min' => 30, 'buffer_min' => 0]);
}

it('generates fixed hourly slots from open to close with zero staff records — slot generation is fully detached from staff', function () {
    seedBookingSettings();
    $day = CarbonImmutable::now('UTC')->addDay();
    BusinessHour::create(['weekday' => $day->dayOfWeek, 'open_time' => '10:00:00', 'close_time' => '13:00:00', 'is_closed' => false]);
    $service = makeBookableService();

    // Deliberately zero Staff rows anywhere in the database.
    expect(Staff::count())->toBe(0);

    $slots = app(AvailabilityEngine::class)->getSlots([$service->id], $day->toDateString(), 'UTC');

    // 10:00-13:00 in fixed 60-min slots: 10:00, 11:00, 12:00, AND 13:00 itself (ad hoc task 35 — the
    // closing time is now an inclusive, real, selectable mark, not just the boundary a slot's own
    // width must fit before) — 4 slots, all open (no bookings yet).
    expect($slots)->toHaveCount(4)
        ->and($slots->pluck('starts_at')->all())->toBe([
            $day->setTime(10, 0)->toIso8601String(),
            $day->setTime(11, 0)->toIso8601String(),
            $day->setTime(12, 0)->toIso8601String(),
            $day->setTime(13, 0)->toIso8601String(),
        ])
        ->and($slots->every(fn (array $slot) => $slot['is_available'] === true))->toBeTrue()
        ->and($slots->first())->not->toHaveKey('staff_id');
});

it('marks a slot unavailable once it reaches the configured max bookings, but still returns it', function () {
    seedBookingSettings();
    Setting::where('key', 'booking.max_bookings_per_slot')->update(['value' => 2]);
    $day = CarbonImmutable::now('UTC')->addDay();
    BusinessHour::create(['weekday' => $day->dayOfWeek, 'open_time' => '10:00:00', 'close_time' => '13:00:00', 'is_closed' => false]);
    $service = makeBookableService();

    $staffA = Staff::factory()->create(['is_active' => true]);
    $staffB = Staff::factory()->create(['is_active' => true]);
    // 2 active bookings (different staff) both overlapping the 10:00-11:00 slot — hits the cap of 2.
    Booking::factory()->create(['staff_id' => $staffA->id, 'starts_at' => $day->setTime(10, 0), 'ends_at' => $day->setTime(10, 30), 'status' => 'confirmed']);
    Booking::factory()->create(['staff_id' => $staffB->id, 'starts_at' => $day->setTime(10, 15), 'ends_at' => $day->setTime(10, 45), 'status' => 'pending']);

    $slots = app(AvailabilityEngine::class)->getSlots([$service->id], $day->toDateString(), 'UTC');

    $tenAm = $slots->firstWhere('starts_at', $day->setTime(10, 0)->toIso8601String());
    $elevenAm = $slots->firstWhere('starts_at', $day->setTime(11, 0)->toIso8601String());

    // Still present — not hidden — but flagged unavailable, per the task's explicit rule.
    expect($tenAm)->not->toBeNull()
        ->and($tenAm['is_available'])->toBeFalse()
        ->and($elevenAm)->not->toBeNull()
        ->and($elevenAm['is_available'])->toBeTrue();
});

it('never disables a slot for service duration/closing-time reasons — capacity is the only thing that can (ad hoc task 35)', function () {
    seedBookingSettings();
    Setting::where('key', 'booking.slot_minutes')->update(['value' => 30]);
    $day = CarbonImmutable::now('UTC')->addDay();
    BusinessHour::create(['weekday' => $day->dayOfWeek, 'open_time' => '10:00:00', 'close_time' => '21:00:00', 'is_closed' => false]);
    $category = ServiceCategory::factory()->create();
    // A long, 10.5-hour total service selection — deliberately far longer than the entire 11-hour
    // business day. Ad hoc task 30/34 would have disabled almost every slot for this; task 35's
    // explicit follow-up spec removes that rule outright — every slot from open to close (inclusive)
    // must stay genuinely selectable regardless of how long the selected services take.
    $service = Service::factory()->create(['service_category_id' => $category->id, 'duration_min' => 600, 'buffer_min' => 30]);

    $slots = app(AvailabilityEngine::class)->getSlots([$service->id], $day->toDateString(), 'UTC');

    // 10:00 AM to 9:00 PM in 30-min steps, inclusive of both ends: 23 marks.
    expect($slots)->toHaveCount(23)
        ->and($slots->every(fn (array $slot) => $slot['is_available'] === true))->toBeTrue()
        ->and($slots->first()['starts_at'])->toBe($day->setTime(10, 0)->toIso8601String())
        ->and($slots->last()['starts_at'])->toBe($day->setTime(21, 0)->toIso8601String());
});

it('ignores a cancelled or no-show booking when counting a slot toward capacity', function () {
    seedBookingSettings();
    Setting::where('key', 'booking.max_bookings_per_slot')->update(['value' => 1]);
    $day = CarbonImmutable::now('UTC')->addDay();
    BusinessHour::create(['weekday' => $day->dayOfWeek, 'open_time' => '10:00:00', 'close_time' => '12:00:00', 'is_closed' => false]);
    $service = makeBookableService();
    $staff = Staff::factory()->create(['is_active' => true]);

    Booking::factory()->create(['staff_id' => $staff->id, 'starts_at' => $day->setTime(10, 0), 'ends_at' => $day->setTime(10, 30), 'status' => 'cancelled']);
    Booking::factory()->create(['staff_id' => $staff->id, 'starts_at' => $day->setTime(10, 0), 'ends_at' => $day->setTime(10, 30), 'status' => 'no_show']);

    $slots = app(AvailabilityEngine::class)->getSlots([$service->id], $day->toDateString(), 'UTC');
    $tenAm = $slots->firstWhere('starts_at', $day->setTime(10, 0)->toIso8601String());

    expect($tenAm['is_available'])->toBeTrue();
});

it('returns no slots on a salon holiday', function () {
    seedBookingSettings();
    $day = CarbonImmutable::now('UTC')->addDay();
    BusinessHour::create(['weekday' => $day->dayOfWeek, 'open_time' => '09:00:00', 'close_time' => '17:00:00', 'is_closed' => false]);
    $service = makeBookableService();

    SalonHoliday::create([
        'name' => 'Test Holiday',
        'starts_at' => $day->toDateString(),
        'ends_at' => $day->toDateString(),
        'is_recurring_yearly' => false,
    ]);

    $slots = app(AvailabilityEngine::class)->getSlots([$service->id], $day->toDateString(), 'UTC');

    expect($slots)->toBeEmpty();
});

it('respects the minimum lead time on the current day', function () {
    Setting::create(['key' => 'business.timezone', 'value' => 'UTC', 'group' => 'business']);
    Setting::create(['key' => 'booking.slot_minutes', 'value' => 60, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_advance_days', 'value' => 60, 'group' => 'booking']);
    Setting::create(['key' => 'booking.hold_minutes', 'value' => 5, 'group' => 'booking']);
    Setting::create(['key' => 'booking.min_lead_minutes', 'value' => 120, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_bookings_per_slot', 'value' => 3, 'group' => 'booking']);

    $now = CarbonImmutable::now('UTC');
    BusinessHour::create(['weekday' => $now->dayOfWeek, 'open_time' => '00:00:00', 'close_time' => '23:00:00', 'is_closed' => false]);
    $service = makeBookableService();

    $slots = app(AvailabilityEngine::class)->getSlots([$service->id], $now->toDateString(), 'UTC');

    $earliestAllowed = $now->addMinutes(120);
    expect($slots->every(fn ($slot) => CarbonImmutable::parse($slot['starts_at'])->gte($earliestAllowed)))->toBeTrue();
});

it('returns no slots past the max advance days window', function () {
    seedBookingSettings();
    $tooFar = CarbonImmutable::now('UTC')->addDays(61);
    BusinessHour::create(['weekday' => $tooFar->dayOfWeek, 'open_time' => '09:00:00', 'close_time' => '17:00:00', 'is_closed' => false]);
    $service = makeBookableService();

    $slots = app(AvailabilityEngine::class)->getSlots([$service->id], $tooFar->toDateString(), 'UTC');

    expect($slots)->toBeEmpty();
});

it('treats a 00:00:00 close time as midnight at the end of the day, not the start', function () {
    seedBookingSettings();
    $day = CarbonImmutable::now('UTC')->addDay();
    BusinessHour::create(['weekday' => $day->dayOfWeek, 'open_time' => '22:00:00', 'close_time' => '00:00:00', 'is_closed' => false]);
    $service = makeBookableService();

    $slots = app(AvailabilityEngine::class)->getSlots([$service->id], $day->toDateString(), 'UTC');

    // 22:00 to (next-day) 00:00 in fixed 60-min slots, inclusive of the closing mark itself (ad hoc
    // task 35): 22:00, 23:00, and midnight itself — 3 slots.
    expect($slots)->toHaveCount(3)
        ->and($slots->last()['starts_at'])->toContain('00:00:00');
});

it('handles a real DST spring-forward transition without producing an invalid time', function () {
    seedBookingSettings();
    Setting::where('key', 'booking.max_advance_days')->first()->update(['value' => 5000]);
    // 2027-03-14 is a real US DST spring-forward date (2:00 AM -> 3:00 AM, America/New_York).
    $day = CarbonImmutable::createFromFormat('Y-m-d', '2027-03-14', 'America/New_York')->startOfDay();
    BusinessHour::create(['weekday' => $day->dayOfWeek, 'open_time' => '01:00:00', 'close_time' => '05:00:00', 'is_closed' => false]);
    $service = makeBookableService();

    $slots = app(AvailabilityEngine::class)->getSlots([$service->id], '2027-03-14', 'America/New_York');

    expect($slots)->not->toBeEmpty();
    $uniqueUtcTimes = $slots->pluck('starts_at')->unique();
    expect($uniqueUtcTimes)->toHaveCount($slots->count());
});

/**
 * `resolveStaffCandidates()`/`isStaffFreeAt()` — the internal, per-staff resolution the public slot
 * list no longer exposes, used only once a customer has picked a capacity slot (`BookingController`
 * `hold()`/`store()`).
 */
function makeStaffWithService(int $weekday, string $start, string $end, int $durationMin = 60, int $bufferMin = 10): array
{
    BusinessHour::firstOrCreate(['weekday' => $weekday], ['open_time' => $start, 'close_time' => $end, 'is_closed' => false]);

    $staff = Staff::factory()->create(['is_active' => true]);
    StaffWorkingHour::create(['staff_id' => $staff->id, 'weekday' => $weekday, 'start_time' => $start, 'end_time' => $end]);

    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create([
        'service_category_id' => $category->id,
        'duration_min' => $durationMin,
        'buffer_min' => $bufferMin,
    ]);
    $staff->services()->attach($service->id);

    return [$staff, $service];
}

it('resolveStaffCandidates finds a genuinely qualifying, free staff member', function () {
    seedBookingSettings();
    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = makeStaffWithService($day->dayOfWeek, '09:00:00', '17:00:00');

    $candidates = app(AvailabilityEngine::class)->resolveStaffCandidates([$service->id], $day->setTime(10, 0), 'UTC');

    expect($candidates->all())->toBe([$staff->id]);
});

it('resolveStaffCandidates excludes a staff member with a conflicting booking', function () {
    seedBookingSettings();
    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = makeStaffWithService($day->dayOfWeek, '09:00:00', '17:00:00', durationMin: 30, bufferMin: 0);

    Booking::factory()->create([
        'staff_id' => $staff->id,
        'starts_at' => $day->setTime(9, 45),
        'ends_at' => $day->setTime(10, 30),
        'status' => 'confirmed',
    ]);

    $candidates = app(AvailabilityEngine::class)->resolveStaffCandidates([$service->id], $day->setTime(10, 0), 'UTC');

    expect($candidates)->toBeEmpty();
});

it('resolveStaffCandidates excludes a staff member during their time off', function () {
    seedBookingSettings();
    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = makeStaffWithService($day->dayOfWeek, '09:00:00', '17:00:00', durationMin: 30, bufferMin: 0);

    StaffTimeOff::create(['staff_id' => $staff->id, 'starts_at' => $day->setTime(10, 0), 'ends_at' => $day->setTime(11, 0), 'reason' => 'Lunch']);

    expect(app(AvailabilityEngine::class)->resolveStaffCandidates([$service->id], $day->setTime(10, 0), 'UTC'))->toBeEmpty()
        ->and(app(AvailabilityEngine::class)->resolveStaffCandidates([$service->id], $day->setTime(11, 0), 'UTC')->all())->toBe([$staff->id]);
});

it('isStaffFreeAt excludes a Redis-held staff+time', function () {
    seedBookingSettings();
    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = makeStaffWithService($day->dayOfWeek, '09:00:00', '17:00:00', durationMin: 30, bufferMin: 0);

    app(SlotHoldService::class)->hold($staff->id, $day->setTime(10, 0)->setTimezone('UTC'));

    expect(app(AvailabilityEngine::class)->isStaffFreeAt($staff->id, [$service->id], $day->setTime(10, 0), 'UTC'))->toBeFalse();
});

it('isStaffFreeAt returns false for a staff member with no working hours that weekday', function () {
    seedBookingSettings();
    $day = CarbonImmutable::now('UTC')->addDay();
    BusinessHour::create(['weekday' => $day->dayOfWeek, 'open_time' => '09:00:00', 'close_time' => '17:00:00', 'is_closed' => false]);

    $staff = Staff::factory()->create(['is_active' => true]);
    // Deliberately no StaffWorkingHour row for this weekday.
    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['service_category_id' => $category->id, 'duration_min' => 30, 'buffer_min' => 0]);
    $staff->services()->attach($service->id);

    expect(app(AvailabilityEngine::class)->isStaffFreeAt($staff->id, [$service->id], $day->setTime(10, 0), 'UTC'))->toBeFalse();
});
