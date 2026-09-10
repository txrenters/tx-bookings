<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Automation;
use App\Models\Team;
use App\Models\User;

class AutomationPolicy
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
    public function view(User $user, Automation $automation): bool
    {
        return $user->belongsToTeam($automation->team);
    }

    /**
     * Determine whether the user can create workflows in the given team.
     *
     * Gated on the permission that governs event types, since a workflow only
     * exists to fire on them and both admins and members hold it.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageEventTypes);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Automation $automation): bool
    {
        return $user->hasTeamPermission($automation->team, TeamPermission::ManageEventTypes);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Automation $automation): bool
    {
        return $this->update($user, $automation);
    }
}
