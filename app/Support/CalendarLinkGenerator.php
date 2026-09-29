<?php

namespace App\Support;

use App\Models\Booking;

class CalendarLinkGenerator
{
    public static function googleForBooking(Booking $booking): string
    {
        $booking->loadMissing(['items.service']);

        $serviceNames = $booking->items->map(fn ($item) => $item->service?->name ?? 'Service')->implode(', ');

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action' => 'TEMPLATE',
            'text' => "Looks Smart Beauty Salon - {$serviceNames}",
            'dates' => $booking->starts_at->utc()->format('Ymd\THis\Z') . '/' . $booking->ends_at->utc()->format('Ymd\THis\Z'),
            'details' => "Booking {$booking->code}.",
            'location' => 'Looks Smart Beauty Salon',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function icsForBooking(Booking $booking): string
    {
        return GuestBookingLinkGenerator::generate($booking, 'booking.calendar.ics');
    }
}
