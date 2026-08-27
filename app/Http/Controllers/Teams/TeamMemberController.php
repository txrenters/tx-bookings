<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\CreateTeamUser;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\CreateTeamMemberRequest;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\User;
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

        $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->update(['role' => $newRole]);

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

        abort_if($team->owner()?->is($user), 403, __('The organization owner cannot be removed.'));

        $team->memberships()
            ->where('user_id', $user->id)
            ->delete();

        app(ActivityLogger::class)->record(
            $team,
            'member.removed',
            'Removed '.$user->name.' from the organization',
            $user,
        );

        if ($user->isCurrentTeam($team)) {
            $user->switchTeam($user->personalTeam());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
