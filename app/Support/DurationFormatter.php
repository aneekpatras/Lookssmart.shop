<?php

namespace App\Support;

/**
 * "2 Hours 30 Mins" — mirrors the frontend's `formatDuration()` in
 * `resources/js/Pages/Admin/Bookings/Index.tsx` so admin UI and outbound emails read identically.
 */
class DurationFormatter
{
    public static function format(int $minutes): string
    {
        if ($minutes <= 0) {
            return '—';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours === 0) {
            return "{$mins} Min" . ($mins === 1 ? '' : 's');
        }

        if ($mins === 0) {
            return "{$hours} Hour" . ($hours === 1 ? '' : 's');
        }

        return "{$hours} Hour" . ($hours === 1 ? '' : 's') . " {$mins} Min" . ($mins === 1 ? '' : 's');
    }
}
