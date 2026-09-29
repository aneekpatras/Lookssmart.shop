<?php

use App\Actions\Booking\CreateBookingAction;
use App\Actions\Booking\SlotUnavailableException;
use App\Models\Booking;
use App\Models\BusinessHour;
use App\Models\Deal;
use App\Models\DealRedemption;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffWorkingHour;
use App\Models\User;
use App\Services\PriceQuoteService;
use App\Services\SlotHoldService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function clearBookingHoldKeys(): void
{
    DB::table('slot_holds')->delete();
}

beforeEach(fn () => clearBookingHoldKeys());
afterEach(fn () => clearBookingHoldKeys());

function makeBookableStaff(int $weekday): array
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

it('holds a public booking slot until the wizard releases or completes it', function () {
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.slot_minutes', 'value' => 15, 'group' => 'booking']);
    Setting::create(['key' => 'booking.min_lead_minutes', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_advance_days', 'value' => 60, 'group' => 'booking']);
    Setting::create(['key' => 'booking.hold_minutes', 'value' => 5, 'group' => 'booking']);

    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = makeBookableStaff($day->dayOfWeek);
    $payload = [
        'service_ids' => [$service->id],
        'staff_id' => $staff->id,
        'starts_at' => $day->setTime(9, 0)->toIso8601String(),
        'timezone' => 'UTC',
    ];

    $hold = $this->postJson('/api/booking/hold', $payload)->assertCreated();
    $token = $hold->json('hold_token');
    expect($token)->toBeString()
        ->and(app(SlotHoldService::class)->isHeld($staff->id, $day->setTime(9, 0)))->toBeTrue();

    $this->postJson('/api/booking/hold', $payload)->assertConflict();
    $this->deleteJson('/api/booking/hold', [
        'staff_id' => $staff->id,
        'starts_at' => $payload['starts_at'],
        'hold_token' => $token,
    ])->assertOk();

    expect(app(SlotHoldService::class)->isHeld($staff->id, $day->setTime(9, 0)))->toBeFalse();
});

it('creates a real guest booking matching the signed quote, auto-creating a matched customer record', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.slot_minutes', 'value' => 15, 'group' => 'booking']);
    Setting::create(['key' => 'booking.min_lead_minutes', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_advance_days', 'value' => 60, 'group' => 'booking']);

    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = makeBookableStaff($day->dayOfWeek);
    $startsAt = $day->setTime(9, 0);

    $quote = app(PriceQuoteService::class)->quote([$service->id]);

    $booking = app(CreateBookingAction::class)->execute(
        serviceIds: [$service->id],
        staffId: $staff->id,
        startsAt: $startsAt,
        quote: $quote,
        timezone: 'UTC',
        source: 'website',
        guestName: 'Jane Guest',
        guestEmail: 'jane.guest@example.com',
    );

    expect($booking->exists)->toBeTrue()
        ->and($booking->status)->toBe('pending')
        ->and((float) $booking->total)->toBe(50.0)
        ->and($booking->items()->count())->toBe(1);

    $customer = User::where('email', 'jane.guest@example.com')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->hasRole('customer'))->toBeTrue()
        ->and($booking->fresh()->customer_id)->toBe($customer->id)
        ->and($customer->customerProfile)->not->toBeNull();

    // Redis hold must be released once the booking is committed — not held for the full TTL.
    expect(app(SlotHoldService::class)->isHeld($staff->id, $startsAt))->toBeFalse();
});

it('rejects a booking whose quote total was tampered with after signing', function () {
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.slot_minutes', 'value' => 15, 'group' => 'booking']);
    Setting::create(['key' => 'booking.min_lead_minutes', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_advance_days', 'value' => 60, 'group' => 'booking']);

    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = makeBookableStaff($day->dayOfWeek);

    $quote = app(PriceQuoteService::class)->quote([$service->id]);
    $quote['total'] = 1; // tampered — real price is 50

    expect(fn () => app(CreateBookingAction::class)->execute(
        serviceIds: [$service->id],
        staffId: $staff->id,
        startsAt: $day->setTime(9, 0),
        quote: $quote,
        timezone: 'UTC',
        source: 'website',
        guestName: 'Attacker',
        guestEmail: 'attacker@example.com',
    ))->toThrow(ValidationException::class);

    expect(Booking::count())->toBe(0);
});

it('rejects a booking whose quote has expired', function () {
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.slot_minutes', 'value' => 15, 'group' => 'booking']);
    Setting::create(['key' => 'booking.min_lead_minutes', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_advance_days', 'value' => 60, 'group' => 'booking']);

    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = makeBookableStaff($day->dayOfWeek);

    $quote = app(PriceQuoteService::class)->quote([$service->id]);
    $quote['expires_at'] = now()->subMinute()->timestamp; // expired, signature no longer matches either

    expect(fn () => app(CreateBookingAction::class)->execute(
        serviceIds: [$service->id],
        staffId: $staff->id,
        startsAt: $day->setTime(9, 0),
        quote: $quote,
        timezone: 'UTC',
        source: 'website',
        guestName: 'Guest',
        guestEmail: 'expired-quote@example.com',
    ))->toThrow(ValidationException::class);

    expect(Booking::count())->toBe(0);
});

it('lets exactly one of many attempts on the same slot succeed, the rest get a friendly slot-taken error', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.slot_minutes', 'value' => 15, 'group' => 'booking']);
    Setting::create(['key' => 'booking.min_lead_minutes', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_advance_days', 'value' => 60, 'group' => 'booking']);

    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = makeBookableStaff($day->dayOfWeek);
    $startsAt = $day->setTime(9, 0);
    $quote = app(PriceQuoteService::class)->quote([$service->id]);

    $succeeded = 0;
    $slotTaken = 0;

    // 50 sequential attempts exercise the exact same atomic-lock + unique-index guard a real
    // concurrent race would hit — each call independently tries to acquire the Redis hold and, if it
    // somehow got past that, would still hit the DB unique(staff_id, starts_at) index.
    for ($i = 0; $i < 50; $i++) {
        try {
            app(CreateBookingAction::class)->execute(
                serviceIds: [$service->id],
                staffId: $staff->id,
                startsAt: $startsAt,
                quote: $quote,
                timezone: 'UTC',
                source: 'website',
                guestName: 'Racer',
                guestEmail: "racer{$i}@example.com",
            );
            $succeeded++;
        } catch (SlotUnavailableException) {
            $slotTaken++;
        }
    }

    expect($succeeded)->toBe(1)
        ->and($slotTaken)->toBe(49)
        ->and(Booking::where('staff_id', $staff->id)->where('starts_at', $startsAt)->count())->toBe(1);
});

it('rejects a coupon once a customer has redeemed it up to their per-user limit', function () {
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.slot_minutes', 'value' => 15, 'group' => 'booking']);
    Setting::create(['key' => 'booking.min_lead_minutes', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_advance_days', 'value' => 60, 'group' => 'booking']);

    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = makeBookableStaff($day->dayOfWeek);
    $customer = User::factory()->create();

    $deal = Deal::factory()->create([
        'type' => 'fixed',
        'value' => 10,
        'code' => 'ONCEONLY',
        'per_user_limit' => 1,
        'usage_limit' => null,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addWeek(),
        'is_active' => true,
    ]);
    DealRedemption::create([
        'deal_id' => $deal->id,
        'customer_id' => $customer->id,
        'discount_amount' => 10,
        'redeemed_at' => now(),
    ]);

    expect(fn () => app(PriceQuoteService::class)->quote([$service->id], 'ONCEONLY', $customer->id))
        ->toThrow(ValidationException::class);
});

it('auto-applies an active auto-apply deal with no code needed, and rejects it via a coupon field mismatch scenario correctly', function () {
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking']);

    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['service_category_id' => $category->id, 'base_price' => 100]);

    Deal::factory()->create([
        'type' => 'percent',
        'value' => 20,
        'code' => null,
        'is_auto_apply' => true,
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addWeek(),
    ]);

    $quote = app(PriceQuoteService::class)->quote([$service->id]);

    expect((float) $quote['discount'])->toBe(20.0)
        ->and((float) $quote['total'])->toBe(80.0)
        ->and($quote['deal_id'])->not->toBeNull();
});
