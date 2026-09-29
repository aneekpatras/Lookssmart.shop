<?php

namespace App\Actions\Booking;

use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Brief §4 / Phase 7 item 3: "a friendly 'slot just taken' error with alternative slots suggested."
 * Carries the alternatives so the controller can return them alongside the error without
 * recomputing availability a second time.
 */
class SlotUnavailableException extends RuntimeException
{
    public function __construct(private readonly Collection $alternatives)
    {
        parent::__construct('That slot was just taken. Please choose another time.');
    }

    public function alternatives(): Collection
    {
        return $this->alternatives;
    }
}
