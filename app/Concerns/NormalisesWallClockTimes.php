<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Keeps wall clock columns in a single H:i:s shape.
 *
 * The UI posts "09:00", Calendly returns "09:00", and the seeded defaults use
 * "09:00:00". SQLite stores a time column verbatim, so without normalising
 * here the same field arrives in two shapes and anything parsing it strictly
 * blows up. Normalising on the model means every writer — request, action,
 * importer, factory — lands the same value.
 */
trait NormalisesWallClockTimes
{
    /**
     * Pad a wall clock string out to H:i:s.
     */
    protected static function normaliseWallClock(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return match (substr_count($value, ':')) {
            0 => $value,
            1 => $value.':00',
            default => $value,
        };
    }

    /**
     * Normalise the start of the window.
     */
    protected function startsAt(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value) => static::normaliseWallClock($value),
        );
    }

    /**
     * Normalise the end of the window.
     */
    protected function endsAt(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value) => static::normaliseWallClock($value),
        );
    }
}
