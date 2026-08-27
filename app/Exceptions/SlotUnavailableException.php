<?php

namespace App\Exceptions;

use RuntimeException;

class SlotUnavailableException extends RuntimeException
{
    /**
     * Create an exception for a slot that was taken before it could be booked.
     */
    public static function taken(): self
    {
        return new self('That time is no longer available.');
    }
}
