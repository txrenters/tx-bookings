<?php

namespace App\Services\Scheduling;

use App\Enums\LimitPeriod;
use App\Models\Booking;
use App\Models\MeetingLimit;
use App\Models\User;
use App\Support\TimeRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Restrictions that apply to a host across every event type they run: the
 * holidays they take off, and the caps on how many meetings they will accept.
 */
class HostRestrictions
{
    public function __construct(protected HolidayCalendar $holidays)
    {
        //
    }

    /**
     * Get the local dates a host is unavailable on for holidays.
     *
     * @return array<int, string> Y-m-d dates.
     */
    public function holidayDates(User $host, TimeRange $window): array
    {
        $country = $host->holiday_country;

        if (blank($country)) {
            return [];
        }

        $keys = $host->enabledHolidays();

        if ($keys === []) {
            return [];
        }

        return $this->holidays->datesFor(
            $country,
            $keys,
            (int) $window->start->format('Y'),
            (int) $window->end->format('Y'),
        );
    }

    /**
     * Get the period keys a host has already filled, so slots falling in them
     * can be dropped.
     *
     * @param  array<int, int>  $userIds
     * @return Collection<int, array<string, array<string, bool>>> Keyed by user id, then period.
     */
    public function filledPeriods(array $userIds, TimeRange $window, string $timezone, ?int $ignoringBookingId = null): Collection
    {
        $limits = MeetingLimit::query()
            ->whereIn('user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        if ($limits->isEmpty()) {
            return new Collection;
        }

        // Count across whole periods, not just the requested window, or a
        // booking earlier in the week would not count towards a weekly cap.
        $bookings = Booking::query()
            ->active()
            ->whereBetween('starts_at', [
                $window->start->startOfMonth()->subWeek(),
                $window->end->endOfMonth()->addWeek(),
            ])
            ->when($ignoringBookingId !== null, fn ($query) => $query->whereKeyNot($ignoringBookingId))
            ->where(function ($query) use ($userIds) {
                $query->whereIn('user_id', $userIds)
                    ->orWhereHas('hosts', fn ($hosts) => $hosts->whereIn('users.id', $userIds));
            })
            ->with('hosts:id')
            ->get(['id', 'user_id', 'starts_at']);

        return (new Collection($userIds))->mapWithKeys(function (int $userId) use ($limits, $bookings, $timezone) {
            /** @var Collection<int, MeetingLimit> $userLimits */
            $userLimits = $limits->get($userId, new Collection);

            if ($userLimits->isEmpty()) {
                return [$userId => []];
            }

            $theirs = $bookings->filter(
                fn (Booking $booking) => $booking->user_id === $userId
                    || $booking->hosts->contains('id', $userId),
            );

            /** @var array<string, array<string, bool>> $filled */
            $filled = [];

            foreach ($userLimits as $limit) {
                $counts = $theirs
                    ->groupBy(fn (Booking $booking) => $limit->period->keyFor(
                        CarbonImmutable::parse($booking->starts_at)->setTimezone($timezone),
                    ))
                    ->map->count();

                $full = [];

                foreach ($counts as $key => $count) {
                    if ($count >= $limit->max_bookings) {
                        $full[(string) $key] = true;
                    }
                }

                $filled[$limit->period->value] = $full;
            }

            return [$userId => $filled];
        });
    }

    /**
     * Determine if a host has room for a meeting starting at the given time.
     *
     * @param  array<string, array<string, bool>>  $filled
     */
    public function hasRoom(array $filled, CarbonImmutable $localStart): bool
    {
        foreach ($filled as $period => $keys) {
            $periodEnum = LimitPeriod::from($period);

            if ($keys[$periodEnum->keyFor($localStart)] ?? false) {
                return false;
            }
        }

        return true;
    }
}
