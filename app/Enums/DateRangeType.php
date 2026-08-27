<?php

namespace App\Enums;

enum DateRangeType: string
{
    case RollingDays = 'rolling_days';
    case FixedRange = 'fixed_range';
    case Indefinite = 'indefinite';

    /**
     * Get the display label for the date range type.
     */
    public function label(): string
    {
        return match ($this) {
            self::RollingDays => 'Days into the future',
            self::FixedRange => 'Within a date range',
            self::Indefinite => 'Indefinitely into the future',
        };
    }
}
