<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Confirmed = 'confirmed';
    case Canceled = 'canceled';
    case Rescheduled = 'rescheduled';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Determine if the booking still occupies its slot.
     */
    public function isActive(): bool
    {
        return $this === self::Confirmed;
    }
}
