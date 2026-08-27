<?php

namespace App\Services\Scheduling;

use App\Models\Booking;
use App\Models\BusyBlock;
use App\Models\EventType;
use App\Support\TimeRange;
use Illuminate\Support\Collection;

class BusyTimeRepository
{
    /**
     * Get the time a host is already committed inside the given window.
     *
     * Bookings are padded with the event type's buffers so back to back
     * meetings keep their breathing room. Bookings belonging to $ignoring are
     * skipped, which lets a group event offer a slot it has already filled
     * partially, and lets a reschedule ignore the booking being moved.
     *
     * @param  array<int, int>  $userIds
     * @return Collection<int, Collection<int, TimeRange>> Keyed by user id.
     */
    public function forHosts(array $userIds, TimeRange $window, ?EventType $excludingEventType = null, ?int $ignoringBookingId = null): Collection
    {
        if ($userIds === []) {
            return new Collection;
        }

        $bookings = $this->bookingRanges($userIds, $window, $excludingEventType, $ignoringBookingId);
        $blocks = $this->busyBlockRanges($userIds, $window);

        return (new Collection($userIds))
            ->mapWithKeys(fn (int $userId) => [
                $userId => TimeRange::merge(
                    ($bookings->get($userId) ?? new Collection)
                        ->merge($blocks->get($userId) ?? new Collection)
                ),
            ]);
    }

    /**
     * Get busy ranges that come from bookings inside this application.
     *
     * @param  array<int, int>  $userIds
     * @return Collection<int, Collection<int, TimeRange>>
     */
    protected function bookingRanges(array $userIds, TimeRange $window, ?EventType $excludingEventType, ?int $ignoringBookingId): Collection
    {
        $bookings = Booking::query()
            ->active()
            ->with('eventType:id,buffer_before_minutes,buffer_after_minutes')
            ->where('starts_at', '<', $window->end)
            ->where('ends_at', '>', $window->start)
            ->when($excludingEventType !== null, fn ($query) => $query->where('event_type_id', '!=', $excludingEventType->id))
            ->when($ignoringBookingId !== null, fn ($query) => $query->whereKeyNot($ignoringBookingId))
            ->where(function ($query) use ($userIds) {
                $query->whereIn('user_id', $userIds)
                    ->orWhereHas('hosts', fn ($hosts) => $hosts->whereIn('users.id', $userIds));
            })
            ->with('hosts:id')
            ->get();

        /** @var Collection<int, Collection<int, TimeRange>> $ranges */
        $ranges = new Collection;

        foreach ($bookings as $booking) {
            $range = TimeRange::make($booking->starts_at, $booking->ends_at)->pad(
                $booking->eventType->buffer_before_minutes,
                $booking->eventType->buffer_after_minutes,
            );

            foreach ($this->hostIdsFor($booking, $userIds) as $userId) {
                $ranges->put($userId, ($ranges->get($userId) ?? new Collection)->push($range));
            }
        }

        return $ranges;
    }

    /**
     * Get the ids of the given hosts that this booking occupies.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, int>
     */
    protected function hostIdsFor(Booking $booking, array $userIds): array
    {
        $ids = $booking->hosts->pluck('id')->push($booking->user_id)->unique()->all();

        return array_values(array_intersect($ids, $userIds));
    }

    /**
     * Get busy ranges synced from connected external calendars.
     *
     * @param  array<int, int>  $userIds
     * @return Collection<int, Collection<int, TimeRange>>
     */
    protected function busyBlockRanges(array $userIds, TimeRange $window): Collection
    {
        return BusyBlock::query()
            ->whereIn('user_id', $userIds)
            ->where('starts_at', '<', $window->end)
            ->where('ends_at', '>', $window->start)
            ->whereHas('calendar', fn ($calendar) => $calendar->where('checks_conflicts', true))
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $blocks) => $blocks
                ->map(fn (BusyBlock $block) => TimeRange::make($block->starts_at, $block->ends_at))
                ->values());
    }
}
