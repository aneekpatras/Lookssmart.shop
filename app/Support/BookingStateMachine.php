<?php

namespace App\Support;

/**
 * Brief §4 / Phase 7 item 9: "Statuses and transitions guarded by a state machine (invalid
 * transitions throw)." A pure lookup table, deliberately not tied to the `Booking` model itself, so
 * both the reschedule/cancel actions here and any later admin action (Phase 12+) share one source of
 * truth for what's legal.
 */
class BookingStateMachine
{
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['checked_in', 'cancelled', 'no_show'],
        'checked_in' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw new InvalidBookingTransitionException($from, $to);
        }
    }
}
