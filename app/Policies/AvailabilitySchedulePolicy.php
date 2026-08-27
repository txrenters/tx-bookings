<?php

namespace App\Policies;

use App\Models\AvailabilitySchedule;
use App\Models\User;

class AvailabilitySchedulePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * Availability is personal: only its owner ever sees or edits it.
     */
    public function view(User $user, AvailabilitySchedule $schedule): bool
    {
        return $schedule->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AvailabilitySchedule $schedule): bool
    {
        return $schedule->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AvailabilitySchedule $schedule): bool
    {
        return $schedule->user_id === $user->id
            && $user->availabilitySchedules()->count() > 1;
    }
}
