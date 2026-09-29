<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Support\Facades\URL;

/**
 * Decision #21: expiry = `starts_at` + 24h grace period, capped at a defensive 90-day ceiling in case
 * `starts_at` is far out (deliberately "short" relative to the booking's own lifecycle, not an
 * arbitrary few-minutes window that would make the link useless for a booking made weeks out).
 */
class GuestBookingLinkGenerator
{
    private const GRACE_HOURS = 24;

    private const MAX_CEILING_DAYS = 90;

    public static function generate(Booking $booking, string $route = 'booking.manage.show'): string
    {
        $graceExpiry = $booking->starts_at->clone()->addHours(self::GRACE_HOURS);
        $ceiling = now()->addDays(self::MAX_CEILING_DAYS);
        $expiresAt = $graceExpiry->lessThan($ceiling) ? $graceExpiry : $ceiling;

        return URL::temporarySignedRoute($route, $expiresAt, ['booking' => $booking->code]);
    }
}
