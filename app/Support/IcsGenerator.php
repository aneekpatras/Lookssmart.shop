<?php

namespace App\Support;

use App\Models\Booking;

/**
 * Brief §4 / Phase 7 item 8: "confirmation email ... with .ics attachment." A minimal, dependency-free
 * RFC 5545 VEVENT generator — one appointment, no recurrence, no timezone component needed since
 * everything is emitted in UTC (`Z` suffix), which every calendar client interprets correctly
 * regardless of the recipient's own timezone.
 */
class IcsGenerator
{
    public static function forBooking(Booking $booking): string
    {
        $serviceNames = $booking->items->map(fn ($item) => $item->service?->name ?? 'Service')->implode(', ');
        $summary = self::escape("Looks Smart Beauty Salon — {$serviceNames}");
        $description = self::escape("Booking {$booking->code}.");
        $uid = "booking-{$booking->id}-{$booking->code}@lookssmartsalon.example";

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Looks Smart Beauty Salon//Booking//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:' . $booking->starts_at->utc()->format('Ymd\THis\Z'),
            'DTEND:' . $booking->ends_at->utc()->format('Ymd\THis\Z'),
            'SUMMARY:' . $summary,
            'DESCRIPTION:' . $description,
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        // RFC 5545 requires CRLF line endings.
        return implode("\r\n", $lines) . "\r\n";
    }

    private static function escape(string $value): string
    {
        return str_replace([',', ';'], ['\\,', '\\;'], $value);
    }
}
