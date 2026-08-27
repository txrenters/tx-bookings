<?php

namespace App\Services\Scheduling;

use App\Models\AvailabilitySchedule;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the "when can people book this" line shown against each event type,
 * resolving every schedule up front so a long list stays one query deep.
 */
class EventTypeAvailabilitySummary
{
    /**
     * Summarise a set of event types, keyed by event type id.
     *
     * @param  Collection<int, EventType>  $eventTypes
     * @return Collection<int, string>
     */
    public function forMany(Collection $eventTypes): Collection
    {
        $hostsByEventType = $eventTypes->mapWithKeys(
            fn (EventType $eventType) => [$eventType->id => $eventType->hostPool()],
        );

        $userIds = $hostsByEventType
            ->flatMap(fn (Collection $hosts) => $hosts->pluck('id'))
            ->unique()
            ->values()
            ->all();

        $schedules = AvailabilitySchedule::query()
            ->with('rules')
            ->whereIn('user_id', $userIds)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $byId = $schedules->keyBy('id');
        $defaultByUser = $schedules->groupBy('user_id')->map->first();

        return $eventTypes->mapWithKeys(function (EventType $eventType) use ($hostsByEventType, $byId, $defaultByUser) {
            $summaries = $hostsByEventType
                ->get($eventType->id, new Collection)
                ->map(fn (User $host) => $this->resolve($eventType, $host, $byId, $defaultByUser)?->summary())
                ->unique()
                ->values()
                ->all();

            return [$eventType->id => $this->describe($summaries)];
        });
    }

    /**
     * Turn the distinct host summaries into a single line.
     *
     * @param  array<int, string|null>  $summaries
     */
    protected function describe(array $summaries): string
    {
        if (count($summaries) > 1) {
            return 'Hours vary by host';
        }

        return $summaries[0] ?? 'No available days or times';
    }

    /**
     * Resolve a host's schedule from the preloaded set.
     *
     * Mirrors ScheduleResolver: the host's override on the event type wins,
     * then the event type's own schedule, then the host's default.
     *
     * @param  Collection<int, AvailabilitySchedule>  $byId
     * @param  Collection<int, AvailabilitySchedule>  $defaultByUser
     */
    protected function resolve(EventType $eventType, User $host, Collection $byId, Collection $defaultByUser): ?AvailabilitySchedule
    {
        $pivot = $host->relationLoaded('pivot') ? $host->getRelation('pivot') : null;
        $pivotScheduleId = $pivot->availability_schedule_id ?? null;

        if ($pivotScheduleId !== null && $byId->has($pivotScheduleId)) {
            return $byId->get($pivotScheduleId);
        }

        if ($eventType->availability_schedule_id !== null
            && $eventType->user_id === $host->id
            && $byId->has($eventType->availability_schedule_id)
        ) {
            return $byId->get($eventType->availability_schedule_id);
        }

        return $defaultByUser->get($host->id);
    }
}
