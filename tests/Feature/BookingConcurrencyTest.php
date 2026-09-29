<?php

use App\Actions\Booking\CreateBookingAction;
use App\Actions\Booking\SlotUnavailableException;
use App\Models\Booking;
use App\Models\BusinessHour;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffWorkingHour;
use App\Services\PriceQuoteService;
use App\Services\SlotHoldService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Phase 14: concurrency/load verification for the booking engine's double-booking guards.
 *
 * WHAT THIS FILE CAN'T PROVE, AND WHY — read before trusting these tests as "concurrency" evidence
 * in the literal sense: Laravel's HTTP test client (`$this->postJson(...)`) runs synchronously in a
 * single PHP process, and this whole suite runs against SQLite `:memory:` (phpunit.xml), a database
 * private to that one process. Neither property allows two attempts to genuinely race here — every
 * call in this file runs to completion before the next line executes, and a real second OS process
 * (which WOULD run concurrently) would get its own empty `:memory:` database and never see this
 * test's seeded staff/service/slot at all. `CreateBookingActionTest.php`'s existing 50-attempt loop
 * already documents this same honest limitation rather than overclaim it. Genuine simultaneous-
 * process load verification — real overlapping requests on the wire at the same instant, against the
 * real dev server and real MariaDB — lives in `scripts/booking-load-test.php` (see its own docblock);
 * results from actually running it this session are recorded in `02-PROJECT-STATE.md`.
 *
 * WHAT IS genuinely new here, not already covered by CreateBookingActionTest.php's Action-level
 * loop: exercising the same guard through the real public HTTP surface end to end (quote → store,
 * not calling CreateBookingAction directly), isolating the DB-unique-index fallback layer
 * specifically (distinct from the Redis-hold layer), a query-count ceiling, and a response-time
 * sanity bound.
 */
function clearConcurrencyHoldKeys(): void
{
    DB::table('slot_holds')->delete();
}

beforeEach(fn () => clearConcurrencyHoldKeys());
afterEach(fn () => clearConcurrencyHoldKeys());

function seedConcurrencyBookableStaff(int $weekday): array
{
    BusinessHour::firstOrCreate(
        ['weekday' => $weekday],
        ['open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_closed' => false],
    );

    $staff = Staff::factory()->create(['is_active' => true]);
    StaffWorkingHour::create(['staff_id' => $staff->id, 'weekday' => $weekday, 'start_time' => '09:00:00', 'end_time' => '18:00:00']);

    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create([
        'service_category_id' => $category->id,
        'duration_min' => 30,
        'buffer_min' => 0,
        'base_price' => 50,
    ]);
    $staff->services()->attach($service->id);

    return [$staff, $service];
}

function seedConcurrencyBookingSettings(): void
{
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.slot_minutes', 'value' => 15, 'group' => 'booking']);
    Setting::create(['key' => 'booking.min_lead_minutes', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_advance_days', 'value' => 60, 'group' => 'booking']);
    Setting::create(['key' => 'booking.hold_minutes', 'value' => 5, 'group' => 'booking']);
}

it('lets only the first of several real HTTP attempts on the same slot actually create a booking', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    seedConcurrencyBookingSettings();

    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = seedConcurrencyBookableStaff($day->dayOfWeek);
    $startsAt = $day->setTime(9, 0);

    $quote = $this->postJson('/api/booking/quote', ['service_ids' => [$service->id]])->assertOk()->json();

    $created = 0;
    $conflicted = 0;

    // Real HTTP layer (routing, throttling, form-request validation, controller, action) — the gap
    // CreateBookingActionTest.php's own direct-Action-call loop doesn't cover. Kept to 8 attempts,
    // under the `throttle:booking` limiter's 10/hour/IP cap, so this proves the real guard through
    // the real public endpoint without needing to disable a security control to make the test fit.
    for ($i = 0; $i < 8; $i++) {
        $response = $this->postJson('/api/booking', [
            'service_ids' => [$service->id],
            'staff_id' => $staff->id,
            'starts_at' => $startsAt->toIso8601String(),
            'timezone' => 'UTC',
            'guest_name' => 'Racer',
            'guest_email' => "racer{$i}@example.com",
            'quote' => $quote,
        ]);

        match ($response->status()) {
            201 => $created++,
            409 => $conflicted++,
            default => test()->fail("Unexpected status {$response->status()}: {$response->getContent()}"),
        };
    }

    expect($created)->toBe(1)
        ->and($conflicted)->toBe(7)
        ->and(Booking::where('staff_id', $staff->id)->where('starts_at', $startsAt)->count())->toBe(1);
});

it('still refuses a second booking via the DB unique index alone, releasing its hold, when the Redis layer is not what caught it', function () {
    // Isolates the SECOND guard layer specifically: a real Booking row is inserted directly (bypassing
    // SlotHoldService entirely, so no Redis key exists for this slot when the Action runs) — proving
    // CreateBookingAction's `QueryException` / 23000 catch path is what rejects this, not the Redis
    // hold, and that it still cleans up the hold it itself acquired rather than leaking it.
    $this->seed(RolesAndPermissionsSeeder::class);
    seedConcurrencyBookingSettings();

    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = seedConcurrencyBookableStaff($day->dayOfWeek);
    $startsAt = $day->setTime(9, 0);

    Booking::create([
        'code' => 'LS-EXISTING',
        'staff_id' => $staff->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(30),
        'status' => 'confirmed',
        'source' => 'admin',
        'total' => 50,
        'discount' => 0,
        'tax' => 0,
    ]);

    expect(app(SlotHoldService::class)->isHeld($staff->id, $startsAt))->toBeFalse();

    $quote = app(PriceQuoteService::class)->quote([$service->id]);

    expect(fn () => app(CreateBookingAction::class)->execute(
        serviceIds: [$service->id],
        staffId: $staff->id,
        startsAt: $startsAt,
        quote: $quote,
        timezone: 'UTC',
        source: 'website',
        guestName: 'Late Racer',
        guestEmail: 'late-racer@example.com',
    ))->toThrow(SlotUnavailableException::class);

    expect(Booking::where('staff_id', $staff->id)->where('starts_at', $startsAt)->count())->toBe(1)
        ->and(app(SlotHoldService::class)->isHeld($staff->id, $startsAt))->toBeFalse();
});

it('keeps the real booking-store endpoint within a bounded, non-N+1 query count', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    seedConcurrencyBookingSettings();

    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = seedConcurrencyBookableStaff($day->dayOfWeek);
    $startsAt = $day->setTime(9, 0);
    $quote = $this->postJson('/api/booking/quote', ['service_ids' => [$service->id]])->assertOk()->json();

    // DB::disableQueryLog() does NOT clear the log, only flushQueryLog() does (same pattern as
    // DashboardControllerTest.php). Unlike that test, there's no known-good baseline to regress
    // against here — this is the first time this endpoint's query count has been measured at all —
    // so 50 is a generous ceiling with real headroom over the actually-observed ~39 (validation,
    // quote verification, the Staff row lock, Booking + BookingItem + BookingStatusLog writes, and
    // synchronously-dispatched notification/database-channel/audit-log writes under QUEUE_CONNECTION
    // =sync), meant to catch an obvious future N+1, not to assert 39 itself is optimal.
    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->postJson('/api/booking', [
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'starts_at' => $startsAt->toIso8601String(),
        'timezone' => 'UTC',
        'guest_name' => 'Query Counter',
        'guest_email' => 'query-counter@example.com',
        'quote' => $quote,
    ]);
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertCreated();
    expect($count)->toBeLessThan(50);
});

it('responds to a real booking creation within a generous, environment-relative time budget', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    seedConcurrencyBookingSettings();

    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = seedConcurrencyBookableStaff($day->dayOfWeek);
    $startsAt = $day->setTime(9, 0);
    $quote = $this->postJson('/api/booking/quote', ['service_ids' => [$service->id]])->assertOk()->json();

    $start = microtime(true);
    $response = $this->postJson('/api/booking', [
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'starts_at' => $startsAt->toIso8601String(),
        'timezone' => 'UTC',
        'guest_name' => 'Timer',
        'guest_email' => 'timer@example.com',
        'quote' => $quote,
    ]);
    $elapsed = microtime(true) - $start;

    $response->assertCreated();
    // 2s is deliberately generous for this shared dev machine (SQLite + queued notification dispatch
    // included) — a sanity bound against a genuine hang/deadlock, not a production SLA.
    expect($elapsed)->toBeLessThan(2.0);
});
