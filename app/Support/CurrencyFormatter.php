<?php

namespace App\Support;

/**
 * Rs. 2,500 — no decimals (Decision, Phase 14 currency migration, matching the frontend's
 * `resources/js/lib/currency.ts`). Display-only: amounts stay stored as `decimal(12,2)` everywhere,
 * this only formats a value for a human-readable string (validation messages, notification payloads,
 * activity-log labels).
 */
class CurrencyFormatter
{
    /**
     * $decimals defaults to 0 for the standard "Rs. 2,500" display shape, but a caller comparing
     * exact amounts down to the cent (e.g. a payment-split mismatch message) can pass 2 to keep the
     * precision that matters for that specific message.
     */
    public static function format(float|string $amount, int $decimals = 0): string
    {
        return 'Rs. ' . number_format((float) $amount, $decimals);
    }
}
