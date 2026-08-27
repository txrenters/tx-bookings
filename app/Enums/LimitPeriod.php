<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum LimitPeriod: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    /**
     * Get the display label for the period.
     */
    public function label(): string
    {
        return match ($this) {
            self::Day => 'per day',
            self::Week => 'per week',
            self::Month => 'per month',
        };
    }

    /**
     * Get the key a date falls under for this period.
     */
    public function keyFor(CarbonImmutable $date): string
    {
        return match ($this) {
            self::Day => $date->toDateString(),
            self::Week => $date->startOfWeek()->toDateString(),
            self::Month => $date->format('Y-m'),
        };
    }

    /**
     * Get the periods as select options.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $period) => [
            'value' => $period->value,
            'label' => $period->label(),
        ], self::cases());
    }
}
