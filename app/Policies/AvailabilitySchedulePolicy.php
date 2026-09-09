<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\AvailabilitySchedule;
use App\Models\Team;
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
     * Personal availability belongs to its owner alone. A shared schedule
     * belongs to an organization, so everyone in it can see the hours their
     * event types keep, and its admins look after them.
     */
    public function view(User $user, AvailabilitySchedule $schedule): bool
    {
        if ($schedule->isShared()) {
            return $schedule->team !== null && $user->belongsToTeam($schedule->team);
        }

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
        if ($schedule->isShared()) {
            return $schedule->team !== null && $this->createShared($user, $schedule->team);
        }

        return $schedule->user_id === $user->id;
    }

    /**
     * Determine whether the user can keep hours on behalf of the organization,
     * which is an administrator's job.
     */
    public function createShared(User $user, Team $team): bool
    {
        return $user->isSuperAdmin() || $user->teamRole($team) === TeamRole::Admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AvailabilitySchedule $schedule): bool
    {
        if ($schedule->isShared()) {
            return $this->update($user, $schedule);
        }

        // Somebody has to keep one: an account with no schedule has no hours
        // and no obvious way back to having any.
        return $schedule->user_id === $user->id
            && $user->availabilitySchedules()->count() > 1;
    }
}
