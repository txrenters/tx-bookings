<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\EventType;
use App\Models\Team;
use App\Models\User;

class EventTypePolicy
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
     */
    public function view(User $user, EventType $eventType): bool
    {
        return $user->belongsToTeam($eventType->team);
    }

    /**
     * Determine whether the user can create event types in the given team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageEventTypes);
    }

    /**
     * Determine whether the user can update the model.
     *
     * Owners always manage their own; team admins manage everyone's.
     */
    public function update(User $user, EventType $eventType): bool
    {
        if (! $user->belongsToTeam($eventType->team)) {
            return false;
        }

        return $eventType->user_id === $user->id
            || $user->hasTeamPermission($eventType->team, TeamPermission::ManageTeamBookings);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EventType $eventType): bool
    {
        return $this->update($user, $eventType);
    }
}
