<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
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
    public function view(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create models.
     *
     * Running a real organization is the prerequisite for starting another
     * one, so a plain member cannot. Personal organizations are excluded
     * deliberately: registration hands every account one and its owner role
     * would otherwise let everybody through, which is the opposite of the
     * rule. Super admins are covered by the central Gate::before grant.
     */
    public function create(User $user): bool
    {
        return $user->teams()
            ->where('teams.is_personal', false)
            ->wherePivot('role', TeamRole::Admin->value)
            ->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::UpdateTeam);
    }

    /**
     * Determine whether the user can leave the team.
     */
    public function leave(User $user, Team $team): bool
    {
        return ! $team->is_personal
            && $user->belongsToTeam($team)
            // An organization has to keep an administrator, so the last one
            // promotes someone else before leaving.
            && ! $this->isLastAdmin($user, $team);
    }

    /**
     * Determine whether the user is the only administrator left.
     */
    public function isLastAdmin(User $user, Team $team): bool
    {
        return $user->teamRole($team) === TeamRole::Admin
            && $team->memberships()->where('role', TeamRole::Admin)->count() === 1;
    }

    /**
     * Determine whether the user can add a member to the team.
     */
    public function addMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::AddMember);
    }

    /**
     * Determine whether the user can update a member's role in the team.
     */
    public function updateMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::UpdateMember);
    }

    /**
     * Determine whether the user can remove a member from the team.
     */
    public function removeMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::RemoveMember);
    }

    /**
     * Determine whether the user can create a user account directly in the team.
     *
     * Deliberately narrower than inviteMember: creating an account outright
     * bypasses the invited person's own consent and the email round-trip, so
     * it is reserved for super admins. Org owners and admins still invite.
     * Note that isSuperAdmin() must be asked explicitly here — teamRole() is
     * null for a super admin, so a permission lookup would deny them.
     */
    public function createMember(User $user, Team $team): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can invite members to the team.
     */
    public function inviteMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::CreateInvitation);
    }

    /**
     * Determine whether the user can cancel invitations.
     */
    public function cancelInvitation(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::CancelInvitation);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Team $team): bool
    {
        return ! $team->is_personal && $user->hasTeamPermission($team, TeamPermission::DeleteTeam);
    }
}
