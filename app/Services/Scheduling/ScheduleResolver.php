<?php

namespace App\Services\Scheduling;

use App\Models\AvailabilitySchedule;
use App\Models\EventType;
use App\Models\User;

class ScheduleResolver
{
    /**
     * Resolve the schedule that governs a host's availability for an event type.
     *
     * The most specific schedule wins: the host's override on the event type,
     * then the event type's own schedule, then the team's hours, then the
     * host's default schedule.
     *
     * An event type's own schedule is normally its owner's, so it governs the
     * owner alone. A SHARED schedule belongs to the organization and is the
     * point of the thing: those hours are the event type's hours, for whoever
     * hosts it.
     *
     * A disabled schedule resolves to null rather than falling through to the
     * next candidate. Substituting different hours for a schedule someone
     * deliberately switched off would be worse than offering nothing.
     */
    public function resolve(EventType $eventType, User $host): ?AvailabilitySchedule
    {
        $pivot = $host->relationLoaded('pivot') ? $host->getRelation('pivot') : null;
        $pivotScheduleId = $pivot?->availability_schedule_id;

        if ($pivotScheduleId !== null) {
            $schedule = AvailabilitySchedule::query()->whereKey((int) $pivotScheduleId)->first();

            if ($schedule !== null) {
                return $schedule->is_active ? $schedule : null;
            }
        }

        if ($eventType->availability_schedule_id !== null) {
            $schedule = $eventType->availabilitySchedule;

            if ($schedule !== null && ($schedule->isShared() || $eventType->user_id === $host->id)) {
                return $schedule->is_active ? $schedule : null;
            }
        }

        // A team's own hours: an event type hosting from Leasing keeps
        // Leasing's hours whoever the round robin lands on.
        if ($eventType->group_id !== null) {
            $schedule = $eventType->group?->availabilitySchedule;

            if ($schedule !== null) {
                return $schedule->is_active ? $schedule : null;
            }
        }

        $schedule = $host->defaultAvailabilitySchedule();

        return $schedule?->is_active ? $schedule : null;
    }
}
