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
     * then the event type's own schedule, then the host's default schedule.
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

        if ($eventType->availability_schedule_id !== null && $eventType->user_id === $host->id) {
            $schedule = $eventType->availabilitySchedule;

            if ($schedule !== null) {
                return $schedule->is_active ? $schedule : null;
            }
        }

        $schedule = $host->defaultAvailabilitySchedule();

        return $schedule?->is_active ? $schedule : null;
    }
}
