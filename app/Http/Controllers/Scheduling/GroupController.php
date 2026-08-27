<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\SaveGroupRequest;
use App\Models\Group;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    /**
     * Display the team's member groups.
     */
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', Group::class);

        $groups = $current_team->groups()
            ->with('members:id,name,email')
            ->withCount('eventTypes')
            ->orderBy('name')
            ->get();

        return Inertia::render('scheduling/groups/Index', [
            'groups' => $groups->map(fn (Group $group) => $this->toPayload($group)),
            'teamMembers' => $current_team->members()->get(['users.id', 'users.name', 'users.email'])
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                ]),
            'canManage' => $request->user()->can('create', [Group::class, $current_team]),
        ]);
    }

    /**
     * Store a new group.
     */
    public function store(SaveGroupRequest $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('create', [Group::class, $current_team]);

        DB::transaction(function () use ($request, $current_team) {
            $group = $current_team->groups()->create($request->safe()->only(['name', 'description']));

            $this->syncMembers($group, $request->validated('member_ids'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group created.')]);

        return to_route('groups.index', ['current_team' => $current_team->slug]);
    }

    /**
     * Update a group.
     */
    public function update(SaveGroupRequest $request, Team $current_team, Group $group): RedirectResponse
    {
        Gate::authorize('update', $group);

        DB::transaction(function () use ($request, $group) {
            $group->update($request->safe()->only(['name', 'description']));

            $this->syncMembers($group, $request->validated('member_ids'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group updated.')]);

        return to_route('groups.index', ['current_team' => $current_team->slug]);
    }

    /**
     * Delete a group.
     *
     * Event types hosting from it fall back to their pinned hosts.
     */
    public function destroy(Team $current_team, Group $group): RedirectResponse
    {
        Gate::authorize('delete', $group);

        $group->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group deleted.')]);

        return to_route('groups.index', ['current_team' => $current_team->slug]);
    }

    /**
     * Sync the members, keeping the order they were given as the priority.
     *
     * @param  array<int, int>  $memberIds
     */
    protected function syncMembers(Group $group, array $memberIds): void
    {
        $payload = [];

        foreach (array_values(array_unique($memberIds)) as $position => $memberId) {
            $payload[$memberId] = ['priority' => $position];
        }

        $group->members()->sync($payload);
    }

    /**
     * Present a group for the listing.
     *
     * @return array<string, mixed>
     */
    protected function toPayload(Group $group): array
    {
        return [
            'id' => $group->id,
            'slug' => $group->slug,
            'name' => $group->name,
            'description' => $group->description,
            'eventTypeCount' => $group->event_types_count,
            'members' => $group->members->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
            ])->values(),
            'memberIds' => $group->members->pluck('id')->values(),
        ];
    }
}
