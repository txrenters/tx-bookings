<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\CreateTeamUser;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\CreateTeamMemberRequest;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Services\Activity\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    /**
     * Create a user account directly inside the organization.
     *
     * Super admin only — see TeamPolicy::createMember.
     */
    public function store(CreateTeamMemberRequest $request, Team $team, CreateTeamUser $createTeamUser): RedirectResponse
    {
        Gate::authorize('createMember', $team);

        $role = TeamRole::from($request->validated('role'));

        $user = $createTeamUser->handle(
            $team,
            $request->validated('name'),
            $request->validated('email'),
            $role,
        );

        app(ActivityLogger::class)->record(
            $team,
            'member.created',
            'Created an account for '.$user->name.' as '.$role->label(),
            $user,
            ['role' => $role->value],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Account created. :name can set a password from the email we just sent.', ['name' => $user->name]),
        ]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Update the specified team member's role.
     */
    public function update(UpdateTeamMemberRequest $request, Team $team, User $user): RedirectResponse
    {
        Gate::authorize('updateMember', $team);

        $newRole = TeamRole::from($request->validated('role'));

        $membership = $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Demoting the last administrator would leave the organization with
        // nobody able to run it.
        abort_if(
            $newRole !== TeamRole::Admin && app(TeamPolicy::class)->isLastAdmin($user, $team),
            403,
            __('The last administrator cannot be demoted.'),
        );

        $membership->update(['role' => $newRole]);

        app(ActivityLogger::class)->record(
            $team,
            'member.role_changed',
            'Changed '.$user->name."'s role to ".$newRole->label(),
            $user,
            ['role' => $newRole->value],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Remove the specified team member.
     */
    public function destroy(Team $team, User $user): RedirectResponse
    {
        Gate::authorize('removeMember', $team);

        abort_if(
            app(TeamPolicy::class)->isLastAdmin($user, $team),
            403,
            __('The last administrator cannot be removed.'),
        );

        $team->memberships()
            ->where('user_id', $user->id)
            ->delete();

        app(ActivityLogger::class)->record(
            $team,
            'member.removed',
            'Removed '.$user->name.' from the organization',
            $user,
        );

        /*
         * Land them somewhere they still belong. personalTeam() is nullable and
         * is null for anyone CreateTeamUser or the invitation join flow made --
         * both place the account straight into an existing organization and
         * deliberately create no personal one -- so passing it straight to
         * switchTeam() raised a TypeError and 500'd the removal.
         *
         * Falling back to any remaining organization keeps them working. Only
         * when the one they were removed from was their last is the current
         * organization cleared.
         */
        if ($user->isCurrentTeam($team)) {
            $next = $user->personalTeam() ?? $user->teams()->first();

            if ($next !== null) {
                $user->switchTeam($next);
            } else {
                $user->forceFill(['current_team_id' => null])->save();
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
