<?php

namespace App\Support;

use App\Models\Booking;

/**
 * Brief Phase 7 item 4: a short, human-readable code (e.g. LS-8F3K2Q) — used in the confirmation
 * email/SMS and as the lookup key for the Decision #21 guest magic-link (never the sequential `id`,
 * which would leak how many bookings exist and let a link be guessed by incrementing).
 */
class BookingCodeGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I — avoids visual ambiguity

    public static function generate(): string
    {
        do {
            $code = 'LS-' . self::randomSegment(6);
        } while (Booking::where('code', $code)->exists());

        return $code;
    }

    private static function randomSegment(int $length): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;

        return collect(range(1, $length))
            ->map(fn () => $alphabet[random_int(0, $max)])
            ->implode('');
    }
}
