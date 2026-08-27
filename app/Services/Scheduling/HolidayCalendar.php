<?php

namespace App\Services\Scheduling;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Public holidays a host can choose to be marked unavailable on.
 *
 * Dates are worked out here rather than fetched, so there is no dependency on
 * an external service and no network call in the availability path.
 */
class HolidayCalendar
{
    /**
     * The countries holidays are known for.
     *
     * @return array<array{value: string, label: string}>
     */
    public function countries(): array
    {
        return [
            ['value' => 'US', 'label' => 'United States'],
        ];
    }

    /**
     * Get the holidays for a country, with the next date each falls on.
     *
     * @return Collection<int, array{key: string, label: string, date: string, isObserved: bool}>
     */
    public function forCountry(string $country, ?CarbonImmutable $from = null): Collection
    {
        $from ??= CarbonImmutable::today();

        return (new Collection($this->definitions($country)))
            ->map(function (array $definition) use ($from) {
                // Roll into next year once this year's date has passed.
                $date = $this->resolve($definition, $from->year);

                if ($date->lessThan($from)) {
                    $date = $this->resolve($definition, $from->year + 1);
                }

                $observed = $this->observe($date, $definition['observed'] ?? false);

                return [
                    'key' => (string) $definition['key'],
                    'label' => (string) $definition['label'],
                    'date' => $observed->toDateString(),
                    'isObserved' => ! $observed->equalTo($date),
                ];
            })
            ->sortBy('date')
            ->values();
    }

    /**
     * Get the dates a set of holidays falls on across a span of years.
     *
     * @param  array<int, string>  $keys
     * @return array<int, string> Y-m-d dates.
     */
    public function datesFor(string $country, array $keys, int $fromYear, int $toYear): array
    {
        if ($keys === []) {
            return [];
        }

        $dates = [];

        foreach ($this->definitions($country) as $definition) {
            if (! in_array($definition['key'], $keys, true)) {
                continue;
            }

            for ($year = $fromYear; $year <= $toYear; $year++) {
                $dates[] = $this->observe(
                    $this->resolve($definition, $year),
                    $definition['observed'] ?? false,
                )->toDateString();
            }
        }

        return array_values(array_unique($dates));
    }

    /**
     * Work out the date a holiday definition falls on in a given year.
     *
     * @param  array<string, mixed>  $definition
     */
    protected function resolve(array $definition, int $year): CarbonImmutable
    {
        if (isset($definition['month'], $definition['day'])) {
            return CarbonImmutable::create($year, $definition['month'], $definition['day']);
        }

        if (($definition['rule'] ?? null) === 'easter') {
            return CarbonImmutable::create($year, 3, 21)->addDays(easter_days($year));
        }

        if (($definition['rule'] ?? null) === 'day_after_thanksgiving') {
            return $this->nthWeekday($year, 11, CarbonImmutable::THURSDAY, 4)->addDay();
        }

        if (($definition['rule'] ?? null) === 'last_weekday') {
            return $this->lastWeekday($year, $definition['month'], $definition['weekday']);
        }

        return $this->nthWeekday($year, $definition['month'], $definition['weekday'], $definition['nth']);
    }

    /**
     * Get the nth given weekday of a month.
     */
    protected function nthWeekday(int $year, int $month, int $weekday, int $nth): CarbonImmutable
    {
        $date = CarbonImmutable::create($year, $month, 1);

        while ($date->dayOfWeek !== $weekday) {
            $date = $date->addDay();
        }

        return $date->addWeeks($nth - 1);
    }

    /**
     * Get the last given weekday of a month.
     */
    protected function lastWeekday(int $year, int $month, int $weekday): CarbonImmutable
    {
        $date = CarbonImmutable::create($year, $month, 1)->endOfMonth()->startOfDay();

        while ($date->dayOfWeek !== $weekday) {
            $date = $date->subDay();
        }

        return $date;
    }

    /**
     * Shift a fixed date holiday to the weekday it is observed on.
     */
    protected function observe(CarbonImmutable $date, bool $observed): CarbonImmutable
    {
        if (! $observed || $date->dayOfWeek !== CarbonImmutable::SATURDAY) {
            return $date;
        }

        return $date->subDay();
    }

    /**
     * Get the holiday definitions for a country.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function definitions(string $country): array
    {
        return match (strtoupper($country)) {
            'US' => [
                ['key' => 'new_years_day', 'label' => "New Year's Day", 'month' => 1, 'day' => 1, 'observed' => true],
                ['key' => 'mlk_day', 'label' => 'Martin Luther King, Jr. Day', 'month' => 1, 'weekday' => CarbonImmutable::MONDAY, 'nth' => 3],
                ['key' => 'presidents_day', 'label' => "Presidents' Day", 'month' => 2, 'weekday' => CarbonImmutable::MONDAY, 'nth' => 3],
                ['key' => 'easter_sunday', 'label' => 'Easter Sunday', 'rule' => 'easter'],
                ['key' => 'memorial_day', 'label' => 'Memorial Day', 'rule' => 'last_weekday', 'month' => 5, 'weekday' => CarbonImmutable::MONDAY],
                ['key' => 'juneteenth', 'label' => 'Juneteenth National Independence Day', 'month' => 6, 'day' => 19, 'observed' => true],
                ['key' => 'independence_day', 'label' => 'Independence Day', 'month' => 7, 'day' => 4, 'observed' => true],
                ['key' => 'labor_day', 'label' => 'Labor Day', 'month' => 9, 'weekday' => CarbonImmutable::MONDAY, 'nth' => 1],
                ['key' => 'columbus_day', 'label' => 'Columbus Day', 'month' => 10, 'weekday' => CarbonImmutable::MONDAY, 'nth' => 2],
                ['key' => 'veterans_day', 'label' => 'Veterans Day', 'month' => 11, 'day' => 11, 'observed' => true],
                ['key' => 'thanksgiving', 'label' => 'Thanksgiving', 'month' => 11, 'weekday' => CarbonImmutable::THURSDAY, 'nth' => 4],
                ['key' => 'day_after_thanksgiving', 'label' => 'Day after Thanksgiving (Black Friday)', 'rule' => 'day_after_thanksgiving'],
                ['key' => 'christmas_eve', 'label' => 'Christmas Eve', 'month' => 12, 'day' => 24],
                ['key' => 'christmas_day', 'label' => 'Christmas Day', 'month' => 12, 'day' => 25, 'observed' => true],
                ['key' => 'new_years_eve', 'label' => "New Year's Eve", 'month' => 12, 'day' => 31],
            ],
            default => [],
        };
    }
}
