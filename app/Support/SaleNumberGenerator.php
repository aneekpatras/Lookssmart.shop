<?php

namespace App\Support;

use App\Models\Sale;

/**
 * Mirrors BookingCodeGenerator's shape — a short, human-readable, collision-checked identifier for
 * receipts, not the sequential `id` (which would leak how many sales have been rung).
 */
class SaleNumberGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I — avoids visual ambiguity

    public static function generate(): string
    {
        do {
            $number = 'POS-' . now()->format('Ymd') . '-' . self::randomSegment(4);
        } while (Sale::withTrashed()->where('sale_number', $number)->exists());

        return $number;
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
