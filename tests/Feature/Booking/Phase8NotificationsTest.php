<?php

use App\Actions\Booking\CancelBookingAction;
use App\Actions\Booking\CreateBookingAction;
use App\Actions\Booking\RescheduleBookingAction;
use App\Channels\MailChannel;
use App\Channels\SmsChannel;
use App\Channels\WhatsappChannel;
use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\BusinessHour;
use App\Models\NotificationLog;
use App\Models\ReminderRule;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffWorkingHour;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingReminder;
use App\Notifications\BookingRescheduled;
use App\Notifications\ReviewRequest;
use App\Notifications\StaffDailySchedule;
use App\Services\PriceQuoteService;
use App\Support\CalendarLinkGenerator;
use App\Support\NotificationPreferenceService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as LaravelNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function clearBookingHoldKeysP8(): void
{
    DB::table('slot_holds')->delete();
}

beforeEach(function () {
    clearBookingHoldKeysP8();
    $this->seed(RolesAndPermissionsSeeder::class);
});
afterEach(fn () => clearBookingHoldKeysP8());

function seedP8BookingSettings(int $cancellationWindowHours = 24): void
{
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.slot_minutes', 'value' => 15, 'group' => 'booking']);
    Setting::create(['key' => 'booking.min_lead_minutes', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_advance_days', 'value' => 60, 'group' => 'booking']);
    Setting::create(['key' => 'booking.cancellation_window_hours', 'value' => $cancellationWindowHours, 'group' => 'booking']);
}

function makeP8BookableStaff(int $weekday): array
{
    BusinessHour::firstOrCreate(['weekday' => $weekday], ['open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_closed' => false]);

    $staff = Staff::factory()->create(['is_active' => true]);
    StaffWorkingHour::create(['staff_id' => $staff->id, 'weekday' => $weekday, 'start_time' => '09:00:00', 'end_time' => '18:00:00']);

    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['service_category_id' => $category->id, 'duration_min' => 30, 'buffer_min' => 0, 'base_price' => 50]);
    $staff->services()->attach($service->id);

    return [$staff, $service];
}

function makeP8Booking(Staff $staff, Service $service, CarbonImmutable $startsAt, string $email): Booking
{
    $quote = app(PriceQuoteService::class)->quote([$service->id]);

    return app(CreateBookingAction::class)->execute(
        serviceIds: [$service->id],
        staffId: $staff->id,
        startsAt: $startsAt,
        quote: $quote,
        timezone: 'UTC',
        source: 'website',
        guestName: 'P8 Guest',
        guestEmail: $email,
    );
}

it('records a real database notification row for a booking confirmation', function () {
    seedP8BookingSettings();
    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = makeP8BookableStaff($day->dayOfWeek);

    $booking = makeP8Booking($staff, $service, $day->setTime(9, 0), 'db-channel@example.com');
    $customer = User::where('email', 'db-channel@example.com')->first();

    $row = $customer->notifications()->where('type', BookingConfirmed::class)->first();
    expect($row)->not->toBeNull()
        ->and($row->data['booking_id'])->toBe($booking->id)
        ->and($row->data['code'])->toBe($booking->code);
});

it('sends a BookingCancelled notification to the customer when a booking is cancelled', function () {
    Notification::fake();
    seedP8BookingSettings(cancellationWindowHours: 24);
    $day = CarbonImmutable::now('UTC')->addDays(3);
    [$staff, $service] = makeP8BookableStaff($day->dayOfWeek);
    $booking = makeP8Booking($staff, $service, $day->setTime(9, 0), 'cancel-notify@example.com');
    $customer = $booking->customer;

    app(CancelBookingAction::class)->execute($booking, 'No longer needed', null);

    Notification::assertSentTo($customer, BookingCancelled::class);
});

it('sends a BookingRescheduled notification to the customer when a booking is rescheduled', function () {
    Notification::fake();
    seedP8BookingSettings(cancellationWindowHours: 24);
    $day = CarbonImmutable::now('UTC')->addDays(3);
    [$staff, $service] = makeP8BookableStaff($day->dayOfWeek);
    $booking = makeP8Booking($staff, $service, $day->setTime(9, 0), 'reschedule-notify@example.com');
    $customer = $booking->customer;

    app(RescheduleBookingAction::class)->execute(
        booking: $booking,
        staffId: $staff->id,
        startsAt: $day->setTime(11, 0),
        timezone: 'UTC',
        reason: 'Time conflict',
        rescheduledBy: null,
    );

    Notification::assertSentTo($customer, BookingRescheduled::class, function ($notification) use ($day, $customer) {
        $data = $notification->toArray($customer);

        return $data['starts_at'] === $day->setTime(11, 0)->toIso8601String()
            && $data['previous_starts_at'] === $day->setTime(9, 0)->toIso8601String();
    });
});

it('logs the database channel with the notifiable email, not a raw relation object', function () {
    seedP8BookingSettings();
    $day = CarbonImmutable::now('UTC')->addDay();
    [$staff, $service] = makeP8BookableStaff($day->dayOfWeek);

    makeP8Booking($staff, $service, $day->setTime(9, 0), 'log-channel@example.com');

    $log = NotificationLog::where('channel', 'database')
        ->where('recipient', 'log-channel@example.com')
        ->first();

    expect($log)->not->toBeNull();
});

it('renders a branded booking confirmation email with the salon identity and booking details', function () {
    seedP8BookingSettings();
    $day = CarbonImmutable::now('UTC')->addDays(3);
    [$staff, $service] = makeP8BookableStaff($day->dayOfWeek);
    $booking = makeP8Booking($staff, $service, $day->setTime(9, 0), 'branded-email@example.com');
    $customer = $booking->customer;

    $mail = (new BookingConfirmed($booking))->toMail($customer);
    $rendered = (string) $mail->render();

    expect($rendered)
        ->toContain('Looks Smart Beauty Salon')
        ->toContain('Booking Confirmed')
        ->toContain($booking->code)
        ->toContain('Manage this booking');
});

it('broadcasts a booking created event to admin and staff dashboard channels', function () {
    Event::fake();
    seedP8BookingSettings();
    $day = CarbonImmutable::now('UTC')->addDays(2);
    [$staff, $service] = makeP8BookableStaff($day->dayOfWeek);

    $booking = makeP8Booking($staff, $service, $day->setTime(10, 0), 'broadcast@example.com');

    Event::assertDispatched(BookingCreated::class, function (BookingCreated $event) use ($booking) {
        return $event->booking->id === $booking->id;
    });
});

it('sends SMS and WhatsApp payloads through the Twilio channels when configured', function () {
    config()->set('services.twilio.sid', 'test-sid');
    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.from_number', '+15551234567');
    config()->set('services.twilio.whatsapp_from', 'whatsapp:+14155238886');

    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
    ]);

    $notifiable = new class
    {
        public string $sms = '+15551234567';

        public string $whatsapp = '+15551234567';

        public function routeNotificationForSms(): string
        {
            return $this->sms;
        }

        public function routeNotificationForWhatsapp(): string
        {
            return $this->whatsapp;
        }
    };

    $notification = new class extends LaravelNotification
    {
        public function via(): array
        {
            return ['sms', 'whatsapp'];
        }

        public function toSms(): string
        {
            return 'Your appointment is confirmed.';
        }

        public function toWhatsapp(): string
        {
            return 'Your appointment is confirmed.';
        }
    };

    (new SmsChannel)->send($notifiable, $notification);
    (new WhatsappChannel)->send($notifiable, $notification);

    Http::assertSentCount(2);
});

it('sends each active staff member a daily schedule digest for today\'s bookings', function () {
    seedP8BookingSettings();
    $today = CarbonImmutable::now('UTC');
    [$staff, $service] = makeP8BookableStaff($today->dayOfWeek);
    $staffUser = User::factory()->create(['email_verified_at' => now()]);
    $staff->update(['user_id' => $staffUser->id]);

    makeP8Booking($staff, $service, $today->setTime(9, 0)->addMinutes(1), 'staff-digest@example.com');

    $this->artisan('app:send-staff-daily-schedules')->assertExitCode(0);

    $row = $staffUser->notifications()->where('type', StaffDailySchedule::class)->first();
    expect($row)->not->toBeNull()
        ->and($row->data['booking_count'])->toBe(1);
});

it('dispatches a due reminder once and records its idempotency key', function () {
    Notification::fake();
    seedP8BookingSettings();
    $clock = CarbonImmutable::parse('2026-09-01 08:00:00', 'UTC');
    [$staff, $service] = makeP8BookableStaff($clock->dayOfWeek);
    $booking = makeP8Booking($staff, $service, $clock->addHours(2), 'reminder@example.com');
    $customer = $booking->customer;
    $rule = ReminderRule::create([
        'event' => 'before_booking',
        'direction' => 'before',
        'offset_minutes' => 120,
        'channels' => ['email'],
        'template' => 'booking-reminder',
        'is_active' => true,
    ]);

    $this->artisan('bookings:send-reminders', ['--now' => $clock->toIso8601String()])->assertExitCode(0);
    $this->artisan('bookings:send-reminders', ['--now' => $clock->toIso8601String()])->assertExitCode(0);

    expect(Notification::sent($customer, BookingReminder::class))->toHaveCount(1)
        ->and(NotificationLog::where('reminder_key', "booking-reminder:{$booking->id}:{$rule->id}")->count())->toBe(1);
});

it('dispatches a review request once after a completed booking', function () {
    Notification::fake();
    seedP8BookingSettings();
    $clock = CarbonImmutable::parse('2026-09-01 12:00:00', 'UTC');
    [$staff, $service] = makeP8BookableStaff($clock->dayOfWeek);
    $booking = makeP8Booking($staff, $service, $clock->subHours(3), 'review@example.com');
    $booking->update(['status' => 'completed', 'ends_at' => $clock->subHours(2)]);
    $customer = $booking->customer;
    $rule = ReminderRule::create([
        'event' => 'review_request',
        'direction' => 'after',
        'offset_minutes' => 60,
        'channels' => ['email'],
        'template' => 'review-request',
        'is_active' => true,
    ]);

    $this->artisan('bookings:request-reviews', ['--now' => $clock->toIso8601String()])->assertExitCode(0);
    $this->artisan('bookings:request-reviews', ['--now' => $clock->toIso8601String()])->assertExitCode(0);

    expect(Notification::sent($customer, ReviewRequest::class))->toHaveCount(1)
        ->and(NotificationLog::where('reminder_key', "booking-review:{$booking->id}:{$rule->id}")->count())->toBe(1);
});

it('provides Google Calendar and signed ICS links for a booking', function () {
    seedP8BookingSettings();
    $clock = CarbonImmutable::parse('2026-09-03 09:00:00', 'UTC');
    [$staff, $service] = makeP8BookableStaff($clock->dayOfWeek);
    $booking = makeP8Booking($staff, $service, $clock, 'calendar@example.com');

    $googleUrl = CalendarLinkGenerator::googleForBooking($booking->fresh()->load(['items.service', 'staff.user']));
    $icsUrl = CalendarLinkGenerator::icsForBooking($booking);
    $response = $this->get($icsUrl);

    expect($googleUrl)->toStartWith('https://calendar.google.com/calendar/render?')
        ->and($googleUrl)->toContain('action=TEMPLATE')
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('content-type'))->toContain('text/calendar')
        ->and($response->streamedContent())->toContain('BEGIN:VEVENT');
});

it('updates one notification preference through a signed unsubscribe link', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->customerProfile()->create();
    $url = NotificationPreferenceService::unsubscribeUrl($user, 'sms');

    $this->get($url)->assertRedirect();

    expect($user->customerProfile->fresh()->sms_opt_out)->toBeTrue();
    $this->get(str_replace('signature=', 'signature=invalid', $url))->assertForbidden();
});

it('suppresses opted-out SMS, WhatsApp, and email channels', function () {
    config()->set('services.twilio.sid', 'test-sid');
    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.from_number', '+15551234567');
    config()->set('services.twilio.whatsapp_from', 'whatsapp:+14155238886');
    Http::fake();
    Mail::fake();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->customerProfile()->create([
        'email_opt_out' => true,
        'sms_opt_out' => true,
        'whatsapp_opt_out' => true,
    ]);
    $notification = new class extends LaravelNotification
    {
        public function toMail(): MailMessage
        {
            return (new MailMessage)->subject('Test')->line('Test');
        }

        public function toSms(): string
        {
            return 'Test';
        }

        public function toWhatsapp(): string
        {
            return 'Test';
        }
    };

    (new MailChannel(app(Illuminate\Notifications\Channels\MailChannel::class)))->send($user, $notification);
    (new SmsChannel)->send($user, $notification);
    (new WhatsappChannel)->send($user, $notification);

    Mail::assertNothingSent();
    Http::assertNothingSent();
});
