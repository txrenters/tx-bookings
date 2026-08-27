<?php

namespace App\Services\Scheduling;

use App\Models\AvailabilityOverride;
use App\Models\AvailabilityRule;
use App\Models\AvailabilitySchedule;
use App\Support\TimeRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class WorkingHoursCalculator
{
    /**
     * Get the working windows for a schedule inside the given UTC window.
     *
     * Rules are written in the schedule's own timezone, so each local day is
     * expanded there and only then converted back to UTC.
     *
     * @return Collection<int, TimeRange>
     */
    public function windowsFor(AvailabilitySchedule $schedule, TimeRange $window): Collection
    {
        if ($window->isEmpty()) {
            return new Collection;
        }

        $timezone = $schedule->timezone ?: config('scheduling.default_timezone');

        /** @var Collection<int, AvailabilityRule> $rules */
        $rules = $schedule->relationLoaded('rules') ? $schedule->rules : $schedule->rules()->get();

        /** @var Collection<int, AvailabilityOverride> $overrides */
        $overrides = $schedule->relationLoaded('overrides') ? $schedule->overrides : $schedule->overrides()->get();

        $rulesByDay = $rules->groupBy(fn (AvailabilityRule $rule) => $rule->day_of_week);
        $overridesByDate = $overrides->groupBy(fn (AvailabilityOverride $override) => $override->date->toDateString());

        /** @var Collection<int, TimeRange> $windows */
        $windows = new Collection;

        $cursor = $window->start->setTimezone($timezone)->startOfDay();
        $lastDay = $window->end->setTimezone($timezone)->endOfDay();

        while ($cursor <= $lastDay) {
            foreach ($this->windowsForDate($cursor, $rulesByDay, $overridesByDate, $timezone) as $range) {
                $clipped = $range->intersect($window);

                if ($clipped !== null) {
                    $windows->push($clipped);
                }
            }

            $cursor = $cursor->addDay()->startOfDay();
        }

        return TimeRange::merge($windows);
    }

    /**
     * Get the working windows for a single local date.
     *
     * @param  Collection<int|string, Collection<int, AvailabilityRule>>  $rulesByDay
     * @param  Collection<string, Collection<int, AvailabilityOverride>>  $overridesByDate
     * @return Collection<int, TimeRange>
     */
    protected function windowsForDate(
        CarbonImmutable $date,
        Collection $rulesByDay,
        Collection $overridesByDate,
        string $timezone,
    ): Collection {
        $overrides = $overridesByDate->get($date->toDateString());

        if ($overrides !== null) {
            if ($overrides->contains(fn (AvailabilityOverride $override) => $override->is_unavailable)) {
                return new Collection;
            }

            return $overrides
                ->filter(fn (AvailabilityOverride $override) => filled($override->starts_at) && filled($override->ends_at))
                ->map(fn (AvailabilityOverride $override) => $this->toRange($date, $override->starts_at, $override->ends_at, $timezone))
                ->filter()
                ->values();
        }

        return ($rulesByDay->get($date->dayOfWeek) ?? new Collection)
            ->map(fn (AvailabilityRule $rule) => $this->toRange($date, $rule->starts_at, $rule->ends_at, $timezone))
            ->filter()
            ->values();
    }

    /**
     * Build a UTC range from a local date and two wall clock times.
     */
    protected function toRange(CarbonImmutable $date, string $startsAt, string $endsAt, string $timezone): ?TimeRange
    {
        $start = $this->applyTime($date, $startsAt, $timezone);
        $end = $this->applyTime($date, $endsAt, $timezone);

        if ($start === null || $end === null || $start >= $end) {
            return null;
        }

        return new TimeRange($start->utc(), $end->utc());
    }

    /**
     * Apply a wall clock time to a local date.
     */
    protected function applyTime(CarbonImmutable $date, string $time, string $timezone): ?CarbonImmutable
    {
        $parts = explode(':', $time);

        if (count($parts) < 2) {
            return null;
        }

        return CarbonImmutable::create(
            $date->year,
            $date->month,
            $date->day,
            (int) $parts[0],
            (int) $parts[1],
            (int) ($parts[2] ?? 0),
            $timezone,
        );
    }
}
