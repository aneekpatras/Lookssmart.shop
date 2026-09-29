<?php

use App\Actions\Booking\CreateBookingAction;
use App\Models\BusinessHour;
use App\Models\NotificationLog;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffWorkingHour;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use App\Notifications\NewBookingAdminAlert;
use App\Services\PriceQuoteService;
use App\Services\SmsNotificationService;
use App\Support\IcsGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function clearBookingHoldKeysNotif(): void
{
    DB::table('slot_holds')->delete();
}

beforeEach(fn () => clearBookingHoldKeysNotif());
afterEach(fn () => clearBookingHoldKeysNotif());

function seedNotifBookingSettings(): void
{
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.slot_minutes', 'value' => 15, 'group' => 'booking']);
    Setting::create(['key' => 'booking.min_lead_minutes', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_advance_days', 'value' => 60, 'group' => 'booking']);
}

function makeNotifBookableStaff(int $weekday): array
{
    BusinessHour::firstOrCreate(['weekday' => $weekday], ['open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_closed' => false]);

    $staff = Staff::factory()->create(['is_active' => true]);
    StaffWorkingHour::create(['staff_id' => $staff->id, 'weekday' => $weekday, 'start_time' => '09:00:00', 'end_time' => '18:00:00']);

    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['service_category_id' => $category->id, 'duration_min' => 30, 'buffer_min' => 0, 'base_price' => 50]);
    $staff->services()->attach($service->id);

    return [$staff, $service];
}

it('sends a confirmation email to the customer and an alert to every admin on a new booking', function () {
    Notification::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    seedNotifBookingSettings();

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $otherAdmin = User::factory()->create(['email_verified_at' => now()]);
    $otherAdmin->assignRole('super-admin');
    $nonAdmin = User::factory()->create(['email_verified_at' => now()]);
    $nonAdmin->assignRole('customer');

    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = makeNotifBookableStaff($day->dayOfWeek);
    $quote = app(PriceQuoteService::class)->quote([$service->id]);

    $booking = app(CreateBookingAction::class)->execute(
        serviceIds: [$service->id],
        staffId: $staff->id,
        startsAt: $day->setTime(9, 0),
        quote: $quote,
        timezone: 'UTC',
        source: 'website',
        guestName: 'Notify Guest',
        guestEmail: 'notify-guest@example.com',
        guestPhone: '+15551234567',
    );

    $customer = User::where('email', 'notify-guest@example.com')->first();

    Notification::assertSentTo($customer, BookingConfirmed::class);
    Notification::assertSentTo($admin, NewBookingAdminAlert::class);
    Notification::assertSentTo($otherAdmin, NewBookingAdminAlert::class);
    Notification::assertNotSentTo($nonAdmin, NewBookingAdminAlert::class);
});

it('logs every sent notification to notification_logs via the global listener', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    seedNotifBookingSettings();

    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = makeNotifBookableStaff($day->dayOfWeek);
    $quote = app(PriceQuoteService::class)->quote([$service->id]);

    app(CreateBookingAction::class)->execute(
        serviceIds: [$service->id],
        staffId: $staff->id,
        startsAt: $day->setTime(9, 0),
        quote: $quote,
        timezone: 'UTC',
        source: 'website',
        guestName: 'Real Notify Guest',
        guestEmail: 'real-notify-guest@example.com',
    );

    // Exactly one row per channel per actual send — not two. This is the exact regression Phase 7
    // caught live: Laravel 11's automatic event discovery PLUS an explicit Event::listen() for the
    // same listener silently doubled every notification_logs row (and, before the fix, every
    // login/lockout email too) since Phase 3. `toHaveCount(1)` per channel, not just "not null", is
    // what would have caught it here. BookingConfirmed legitimately sends via BOTH 'mail' and
    // 'database' since Phase 8 sub-step 1, so two total rows for this recipient is correct — the
    // regression guard is that each individual channel logs exactly once, not zero-or-two.
    $logs = NotificationLog::where('recipient', 'real-notify-guest@example.com')->get();
    expect($logs)->toHaveCount(2);

    $mailLog = $logs->firstWhere('channel', 'mail');
    expect($mailLog)->not->toBeNull()
        ->and($mailLog->status)->toBe('sent')
        ->and($mailLog->sent_at)->not->toBeNull();

    expect($logs->where('channel', 'mail'))->toHaveCount(1);
    expect($logs->where('channel', 'database'))->toHaveCount(1);
});

it('generates a valid ICS VEVENT for a booking', function () {
    seedNotifBookingSettings();
    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = makeNotifBookableStaff($day->dayOfWeek);
    $quote = app(PriceQuoteService::class)->quote([$service->id]);
    $this->seed(RolesAndPermissionsSeeder::class);

    $booking = app(CreateBookingAction::class)->execute(
        serviceIds: [$service->id],
        staffId: $staff->id,
        startsAt: $day->setTime(9, 0),
        quote: $quote,
        timezone: 'UTC',
        source: 'website',
        guestName: 'Ics Guest',
        guestEmail: 'ics-guest@example.com',
    );

    $ics = IcsGenerator::forBooking($booking->fresh()->load(['items.service', 'staff.user']));

    expect($ics)->toContain('BEGIN:VCALENDAR')
        ->and($ics)->toContain('BEGIN:VEVENT')
        ->and($ics)->toContain('UID:booking-' . $booking->id . '-' . $booking->code . '@lookssmartsalon.example')
        ->and($ics)->toContain('DTSTART:' . $day->setTime(9, 0)->utc()->format('Ymd\THis\Z'))
        ->and($ics)->toContain('END:VEVENT')
        ->and($ics)->toContain('END:VCALENDAR');
});

it('stays inert and logs a clear reason when Twilio is not configured', function () {
    config(['services.twilio.sid' => null, 'services.twilio.auth_token' => null]);

    app(SmsNotificationService::class)->sendBookingConfirmation('+15551234567', 'LS-TEST01');

    $log = NotificationLog::where('recipient', '+15551234567')->first();
    expect($log)->not->toBeNull()
        ->and($log->channel)->toBe('sms')
        ->and($log->status)->toBe('failed')
        ->and($log->error)->toContain('not configured');
});
