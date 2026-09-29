<?php

namespace App\Support;

use RuntimeException;

class InvalidBookingTransitionException extends RuntimeException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct("A booking cannot move from \"{$from}\" to \"{$to}\".");
    }
}
