<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
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
     *
     * A pending booking holds its slot so it cannot be double-booked while a
     * host decides, but it is not yet confirmed: use isConfirmed() to gate
     * anything that treats the meeting as definitely happening.
     */
    public function isActive(): bool
    {
        return $this === self::Pending || $this === self::Confirmed;
    }

    /**
     * Determine if the booking is definitely happening.
     */
    public function isConfirmed(): bool
    {
        return $this === self::Confirmed;
    }
}
