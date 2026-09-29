<?php

use App\Actions\Booking\CreateBookingAction;
use App\Models\Booking;
use App\Models\BusinessHour;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\StaffWorkingHour;
use App\Services\PriceQuoteService;
use App\Support\GuestBookingLinkGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function clearBookingHoldKeysGuest(): void
{
    DB::table('slot_holds')->delete();
}

beforeEach(function () {
    clearBookingHoldKeysGuest();
    $this->seed(RolesAndPermissionsSeeder::class);
});
afterEach(fn () => clearBookingHoldKeysGuest());

function seedGuestBookingSettings(): void
{
    Setting::create(['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.slot_minutes', 'value' => 15, 'group' => 'booking']);
    Setting::create(['key' => 'booking.min_lead_minutes', 'value' => 0, 'group' => 'booking']);
    Setting::create(['key' => 'booking.max_advance_days', 'value' => 60, 'group' => 'booking']);
    Setting::create(['key' => 'booking.cancellation_window_hours', 'value' => 24, 'group' => 'booking']);
}

function makeGuestBooking(): Booking
{
    $day = CarbonImmutable::now('UTC')->addDays(3);
    BusinessHour::firstOrCreate(['weekday' => $day->dayOfWeek], ['open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_closed' => false]);
    $staff = Staff::factory()->create(['is_active' => true]);
    StaffWorkingHour::create(['staff_id' => $staff->id, 'weekday' => $day->dayOfWeek, 'start_time' => '09:00:00', 'end_time' => '18:00:00']);
    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['service_category_id' => $category->id, 'duration_min' => 30, 'buffer_min' => 0, 'base_price' => 60]);
    $staff->services()->attach($service->id);

    $quote = app(PriceQuoteService::class)->quote([$service->id]);

    return app(CreateBookingAction::class)->execute(
        serviceIds: [$service->id],
        staffId: $staff->id,
        startsAt: $day->setTime(9, 0),
        quote: $quote,
        timezone: 'UTC',
        source: 'website',
        guestName: 'Magic Link Guest',
        guestEmail: 'magic-link-' . uniqid() . '@example.com',
    );
}

it('lets a guest view their booking via a valid signed link, with no session ever established', function () {
    seedGuestBookingSettings();
    $booking = makeGuestBooking();
    $url = GuestBookingLinkGenerator::generate($booking);

    $response = $this->get($url);

    $response->assertOk()->assertJson(['code' => $booking->code, 'status' => 'pending']);
    $this->assertGuest();
});

it('rejects a tampered signed link', function () {
    seedGuestBookingSettings();
    $booking = makeGuestBooking();
    $url = GuestBookingLinkGenerator::generate($booking);
    $tampered = str_replace('signature=', 'signature=tampered', $url);

    $this->get($tampered)->assertForbidden();
});

it('rejects an expired signed link', function () {
    seedGuestBookingSettings();
    $booking = makeGuestBooking();

    $expiredUrl = URL::temporarySignedRoute('booking.manage.show', now()->subMinute(), ['booking' => $booking->code]);

    $this->get($expiredUrl)->assertForbidden();
});

it('lets a guest cancel via the magic link, respecting the same policy window as an authenticated cancel', function () {
    seedGuestBookingSettings();
    $booking = makeGuestBooking();

    // A signature is only valid for the exact route it was generated for — the real flow is: open the
    // "show" link, then use the cancel_url THAT RESPONSE hands back (see BookingManageController),
    // not a hand-modified copy of the show URL.
    $showResponse = $this->get(GuestBookingLinkGenerator::generate($booking));
    $cancelUrl = $showResponse->json('cancel_url');

    $response = $this->post($cancelUrl, ['reason' => 'Plans changed']);

    $response->assertOk()->assertJson(['status' => 'cancelled']);
    expect($booking->fresh()->cancellation_reason)->toBe('Plans changed');
    $this->assertGuest();
});

it('returns 410 for a valid-signature link once the booking is cancelled', function () {
    seedGuestBookingSettings();
    $booking = makeGuestBooking();
    $booking->update(['status' => 'cancelled']);
    $url = GuestBookingLinkGenerator::generate($booking);

    $this->get($url)->assertStatus(410);
});

it('returns 410 for a valid-signature link once the booking is completed', function () {
    seedGuestBookingSettings();
    $booking = makeGuestBooking();
    $booking->update(['status' => 'completed']);
    $url = GuestBookingLinkGenerator::generate($booking);

    $this->get($url)->assertStatus(410);
});

it('caps the link expiry at 90 days even for a booking far in the future', function () {
    seedGuestBookingSettings();
    Setting::where('key', 'booking.max_advance_days')->first()->update(['value' => 5000]);
    $day = CarbonImmutable::now('UTC')->addDays(200);
    BusinessHour::firstOrCreate(['weekday' => $day->dayOfWeek], ['open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_closed' => false]);
    $staff = Staff::factory()->create(['is_active' => true]);
    StaffWorkingHour::create(['staff_id' => $staff->id, 'weekday' => $day->dayOfWeek, 'start_time' => '09:00:00', 'end_time' => '18:00:00']);
    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['service_category_id' => $category->id, 'duration_min' => 30, 'buffer_min' => 0, 'base_price' => 60]);
    $staff->services()->attach($service->id);
    $quote = app(PriceQuoteService::class)->quote([$service->id]);

    $booking = app(CreateBookingAction::class)->execute(
        serviceIds: [$service->id],
        staffId: $staff->id,
        startsAt: $day->setTime(9, 0),
        quote: $quote,
        timezone: 'UTC',
        source: 'website',
        guestName: 'Far Future Guest',
        guestEmail: 'far-future@example.com',
    );

    $url = GuestBookingLinkGenerator::generate($booking);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
    $expires = CarbonImmutable::createFromTimestamp((int) $params['expires']);

    // starts_at (+200 days) + 24h grace would be ~201 days out — the 90-day ceiling must win.
    expect($expires->diffInDays(now(), true))->toBeLessThanOrEqual(91);
});
