<?php

use App\Actions\Booking\CancelBookingAction;
use App\Actions\Booking\CreateBookingAction;
use App\Actions\Booking\RescheduleBookingAction;
use App\Actions\Booking\SlotUnavailableException;
use App\Models\Booking;
use App\Models\BusinessHour;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffWorkingHour;
use App\Services\PriceQuoteService;
use App\Support\InvalidBookingTransitionException;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function clearBookingHoldKeysCR(): void
{
    DB::table('slot_holds')->delete();
}

beforeEach(function () {
    clearBookingHoldKeysCR();
    $this->seed(RolesAndPermissionsSeeder::class);
});
afterEach(fn () => clearBookingHoldKeysCR());

function seedCRBookingSettings(int $cancellationWindowHours = 24): void
{
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.slot_minutes', 'value' => 15, 'group' => 'booking']);
    Setting::create(['key' => 'booking.min_lead_minutes', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_advance_days', 'value' => 60, 'group' => 'booking']);
    Setting::create(['key' => 'booking.cancellation_window_hours', 'value' => $cancellationWindowHours, 'group' => 'booking']);
}

function makeCRBookableStaff(int $weekday): array
{
    BusinessHour::firstOrCreate(['weekday' => $weekday], ['open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_closed' => false]);

    $staff = Staff::factory()->create(['is_active' => true]);
    StaffWorkingHour::create(['staff_id' => $staff->id, 'weekday' => $weekday, 'start_time' => '09:00:00', 'end_time' => '18:00:00']);

    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['service_category_id' => $category->id, 'duration_min' => 30, 'buffer_min' => 0, 'base_price' => 50]);
    $staff->services()->attach($service->id);

    return [$staff, $service];
}

function makeCRBooking(Staff $staff, Service $service, CarbonImmutable $startsAt): Booking
{
    $quote = app(PriceQuoteService::class)->quote([$service->id]);

    return app(CreateBookingAction::class)->execute(
        serviceIds: [$service->id],
        staffId: $staff->id,
        startsAt: $startsAt,
        quote: $quote,
        timezone: 'UTC',
        source: 'website',
        guestName: 'Test Guest',
        guestEmail: 'cr-test-' . uniqid() . '@example.com',
    );
}

it('cancels a booking within the policy window and frees the exact slot for rebooking', function () {
    seedCRBookingSettings(cancellationWindowHours: 24);
    $day = CarbonImmutable::now('UTC')->addDays(3);
    [$staff, $service] = makeCRBookableStaff($day->dayOfWeek);
    $startsAt = $day->setTime(9, 0);
    $booking = makeCRBooking($staff, $service, $startsAt);

    app(CancelBookingAction::class)->execute($booking, 'Change of plans', null);

    expect($booking->fresh()->status)->toBe('cancelled')
        ->and($booking->fresh()->cancellation_reason)->toBe('Change of plans');

    // The exact same slot must be genuinely rebookable now — not just "look free" via the
    // AvailabilityEngine's status filter, but actually pass the DB's status-aware unique index.
    $rebooked = makeCRBooking($staff, $service, $startsAt);
    expect($rebooked->exists)->toBeTrue()
        ->and($rebooked->status)->toBe('pending');
});

it('refuses to cancel within the policy window unless bypassed', function () {
    seedCRBookingSettings(cancellationWindowHours: 48);
    $day = CarbonImmutable::now('UTC')->addHours(2); // well inside a 48h window
    [$staff, $service] = makeCRBookableStaff($day->dayOfWeek);
    $booking = makeCRBooking($staff, $service, $day);

    expect(fn () => app(CancelBookingAction::class)->execute($booking, null, null))
        ->toThrow(ValidationException::class);
    expect($booking->fresh()->status)->toBe('pending');

    // Staff/admin (bookings.manage) can bypass the window for a genuine business reason.
    app(CancelBookingAction::class)->execute($booking, 'Staff override', null, bypassPolicyWindow: true);
    expect($booking->fresh()->status)->toBe('cancelled');
});

it('refuses to cancel a booking that is already completed', function () {
    seedCRBookingSettings();
    $day = CarbonImmutable::now('UTC')->addDays(3);
    [$staff, $service] = makeCRBookableStaff($day->dayOfWeek);
    $booking = makeCRBooking($staff, $service, $day->setTime(9, 0));
    $booking->update(['status' => 'completed']);

    expect(fn () => app(CancelBookingAction::class)->execute($booking, null, null, bypassPolicyWindow: true))
        ->toThrow(InvalidBookingTransitionException::class);
});

it('reschedules a booking to a new slot and logs the change', function () {
    seedCRBookingSettings(cancellationWindowHours: 24);
    $day = CarbonImmutable::now('UTC')->addDays(3);
    [$staff, $service] = makeCRBookableStaff($day->dayOfWeek);
    $originalStart = $day->setTime(9, 0);
    $newStart = $day->setTime(11, 0);
    $booking = makeCRBooking($staff, $service, $originalStart);

    $rescheduled = app(RescheduleBookingAction::class)->execute(
        booking: $booking,
        staffId: $staff->id,
        startsAt: $newStart,
        timezone: 'UTC',
        reason: 'Customer requested a later time',
        rescheduledBy: null,
    );

    expect($rescheduled->starts_at->equalTo($newStart))->toBeTrue()
        ->and($rescheduled->status)->toBe('pending');

    // The OLD slot must be genuinely free again (status-aware unique index applies here too, since
    // the row's staff_id/starts_at changed rather than a new row being inserted).
    $rebookedOldSlot = makeCRBooking($staff, $service, $originalStart);
    expect($rebookedOldSlot->exists)->toBeTrue();

    $log = $booking->statusLogs()->latest('id')->first();
    expect($log->reason)->toBe('Customer requested a later time');
});

it('rejects a reschedule onto a slot someone else already holds', function () {
    seedCRBookingSettings(cancellationWindowHours: 24);
    $day = CarbonImmutable::now('UTC')->addDays(3);
    [$staff, $service] = makeCRBookableStaff($day->dayOfWeek);
    $bookingToMove = makeCRBooking($staff, $service, $day->setTime(9, 0));
    $occupiedSlot = $day->setTime(11, 0);
    makeCRBooking($staff, $service, $occupiedSlot); // occupies 11:00 with a different booking

    expect(fn () => app(RescheduleBookingAction::class)->execute(
        booking: $bookingToMove,
        staffId: $staff->id,
        startsAt: $occupiedSlot,
        timezone: 'UTC',
        reason: null,
        rescheduledBy: null,
    ))->toThrow(SlotUnavailableException::class);

    expect($bookingToMove->fresh()->starts_at->equalTo($day->setTime(9, 0)))->toBeTrue();
});

it('refuses to reschedule within the policy window unless bypassed', function () {
    seedCRBookingSettings(cancellationWindowHours: 48);
    $day = CarbonImmutable::now('UTC')->addHours(2);
    [$staff, $service] = makeCRBookableStaff($day->dayOfWeek);
    $booking = makeCRBooking($staff, $service, $day);

    expect(fn () => app(RescheduleBookingAction::class)->execute(
        booking: $booking,
        staffId: $staff->id,
        startsAt: $day->addDay(),
        timezone: 'UTC',
        reason: null,
        rescheduledBy: null,
    ))->toThrow(ValidationException::class);
});
