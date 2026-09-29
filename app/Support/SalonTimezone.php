<?php

namespace App\Support;

/**
 * The salon operates in Lahore, Pakistan — PKT (UTC+5), no DST. Bookings are always stored in UTC
 * (`Booking::$casts['starts_at']` — Brief §5, never local-time storage), so anything that displays
 * a time to a human (email copy, the customer portal) must explicitly convert to this timezone
 * first. `config('app.timezone')`/`business.timezone` are still `UTC` (Known Issue: the app-wide
 * switch to Asia/Karachi is a separate, larger change — see 02-PROJECT-STATE.md §10 #38) — this
 * constant is scoped to display formatting only, not a change to how anything is stored or computed.
 */
class SalonTimezone
{
    public const DISPLAY = 'Asia/Karachi';
}
