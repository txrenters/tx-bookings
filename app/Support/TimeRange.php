<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * A half open time interval: the start is included, the end is not.
 */
final readonly class TimeRange
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
        //
    }

    /**
     * Create a range from any date representation.
     */
    public static function make(mixed $start, mixed $end): self
    {
        return new self(CarbonImmutable::parse($start), CarbonImmutable::parse($end));
    }

    /**
     * Determine if the range contains no time at all.
     */
    public function isEmpty(): bool
    {
        return $this->start >= $this->end;
    }

    /**
     * Get the length of the range in minutes.
     */
    public function minutes(): int
    {
        return (int) $this->start->diffInMinutes($this->end);
    }

    /**
     * Determine if this range shares any time with the given range.
     */
    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }

    /**
     * Determine if the given range sits entirely inside this one.
     */
    public function contains(self $other): bool
    {
        return $other->start >= $this->start && $other->end <= $this->end;
    }

    /**
     * Grow the range by the given number of minutes on each side.
     */
    public function pad(int $before, int $after): self
    {
        return new self(
            $this->start->subMinutes($before),
            $this->end->addMinutes($after),
        );
    }

    /**
     * Get the part of this range that also falls inside the given range.
     */
    public function intersect(self $other): ?self
    {
        $start = $this->start->max($other->start);
        $end = $this->end->min($other->end);

        return $start < $end ? new self($start, $end) : null;
    }

    /**
     * Remove the given ranges from this one, returning what is left.
     *
     * @param  iterable<self>  $ranges
     * @return Collection<int, self>
     */
    public function subtract(iterable $ranges): Collection
    {
        /** @var Collection<int, self> $remaining */
        $remaining = new Collection([$this]);

        foreach (self::merge($ranges) as $blocked) {
            $remaining = $remaining
                ->flatMap(fn (self $range) => self::difference($range, $blocked))
                ->values();
        }

        return $remaining->reject(fn (self $range) => $range->isEmpty())->values();
    }

    /**
     * Remove one range from another, returning the pieces that are left.
     *
     * @return Collection<int, self>
     */
    public static function difference(self $range, self $blocked): Collection
    {
        /** @var Collection<int, self> $pieces */
        $pieces = new Collection;

        if (! $range->overlaps($blocked)) {
            return $pieces->push($range);
        }

        if ($range->start < $blocked->start) {
            $pieces->push(new self($range->start, $blocked->start));
        }

        if ($blocked->end < $range->end) {
            $pieces->push(new self($blocked->end, $range->end));
        }

        return $pieces;
    }

    /**
     * Merge overlapping and touching ranges into the smallest equivalent set.
     *
     * @param  iterable<self>  $ranges
     * @return Collection<int, self>
     */
    public static function merge(iterable $ranges): Collection
    {
        $sorted = (new Collection($ranges))
            ->reject(fn (self $range) => $range->isEmpty())
            ->sortBy(fn (self $range) => $range->start->getTimestamp())
            ->values();

        /** @var Collection<int, self> $merged */
        $merged = new Collection;

        foreach ($sorted as $range) {
            /** @var self|null $last */
            $last = $merged->last();

            if ($last !== null && $range->start <= $last->end) {
                $merged->pop();
                $merged->push(new self($last->start, $last->end->max($range->end)));

                continue;
            }

            $merged->push($range);
        }

        return $merged;
    }

    /**
     * Get the ranges shared by every one of the given sets.
     *
     * @param  iterable<iterable<self>>  $sets
     * @return Collection<int, self>
     */
    public static function intersectAll(iterable $sets): Collection
    {
        $sets = new Collection($sets);

        if ($sets->isEmpty()) {
            return new Collection;
        }

        /** @var Collection<int, self> $result */
        $result = self::merge($sets->shift());

        foreach ($sets as $set) {
            $other = self::merge($set);

            $result = $result
                ->flatMap(fn (self $range) => $other
                    ->map(fn (self $candidate) => $range->intersect($candidate))
                    ->filter()
                    ->values())
                ->values();

            if ($result->isEmpty()) {
                break;
            }
        }

        return $result;
    }
}
