<?php

namespace App\Http\Controllers\Settings;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\CalendarAccount;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserDirectoryController extends Controller
{
    /**
     * How many accounts are listed at a time.
     */
    protected int $perPage = 25;

    /**
     * List every account and the organizations it belongs to.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('manageUsers');

        $search = $request->string('search')->toString();

        $users = User::query()
            ->with([
                'teams:id,name,slug,is_personal',
                'groups:id,name',
                'calendarAccounts:id,user_id,provider,sync_error,last_synced_at',
            ])
            ->when(filled($search), fn ($query) => $query->where(
                fn ($match) => $match
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"),
            ))
            ->orderBy('name')
            ->paginate($this->perPage)
            ->withQueryString();

        return Inertia::render('users/Index', [
            'users' => $users->through(fn (User $user) => $this->toPayload($user))->items(),
            'search' => $search,
            'page' => $users->currentPage(),
            'lastPage' => $users->lastPage(),
            'total' => $users->total(),
            // Owner included: a super admin may hand an organization over from
            // here, and the controller demotes the previous one.
            'roles' => array_map(
                fn (TeamRole $role) => ['value' => $role->value, 'label' => $role->label()],
                TeamRole::cases(),
            ),
        ]);
    }

    /**
     * Email the user a link to set a new password.
     *
     * Deliberately a reset link rather than a password this page chooses: a
     * usable password never passes through the operator's hands, the way
     * CreateTeamUser already works.
     */
    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageUsers');

        Password::sendResetLink(['email' => $user->email]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Password reset link sent to :email.', ['email' => $user->email]),
        ]);

        return back();
    }

    /**
     * Change the role a user holds in one of their organizations.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageUsers');

        $validated = $request->validate([
            'team_id' => ['required', 'integer'],
            'role' => ['required', Rule::enum(TeamRole::class)],
        ]);

        $membership = $user->teamMemberships()
            ->where('team_id', $validated['team_id'])
            ->firstOrFail();

        $role = TeamRole::from($validated['role']);

        DB::transaction(function () use ($membership, $role, $user, $validated) {
            // One owner per organization, the same rule the team screen keeps.
            if ($role === TeamRole::Owner) {
                Membership::query()
                    ->where('team_id', $validated['team_id'])
                    ->where('user_id', '!=', $user->id)
                    ->where('role', TeamRole::Owner)
                    ->update(['role' => TeamRole::Admin]);
            }

            $membership->update(['role' => $role]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name is now :role.', ['name' => $user->name, 'role' => $role->label()]),
        ]);

        return back();
    }

    /**
     * Delete an account outright.
     *
     * Everything hanging off it goes with the foreign keys: memberships, event
     * types, availability and the bookings it hosts.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageUsers');

        if ($request->user()->is($user)) {
            return back()->withErrors(['user' => __('You cannot delete your own account here.')]);
        }

        $user->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name has been deleted.', ['name' => $user->name]),
        ]);

        return back();
    }

    /**
     * Describe one account for the directory.
     *
     * @return array<string, mixed>
     */
    protected function toPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'initials' => $this->initials($user->name),
            'isSuperAdmin' => $user->isSuperAdmin(),
            'isVerified' => $user->email_verified_at !== null,
            'joinedAt' => $user->created_at?->toFormattedDateString(),
            'bookingUrl' => $user->booking_slug === null
                ? null
                : route('book.page', ['page' => $user->booking_slug]),
            'groups' => $user->groups->pluck('name')->values(),
            'calendar' => $this->calendarStatus($user),
            'organizations' => $user->teams
                ->map(fn (Team $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'isPersonal' => (bool) $team->is_personal,
                    // teams() carries a plain pivot, so the role arrives as a
                    // string rather than the cast Membership enum.
                    'role' => $team->getRelation('pivot')->role,
                    'roleLabel' => TeamRole::from($team->getRelation('pivot')->role)->label(),
                ])
                ->values(),
        ];
    }

    /**
     * Describe the state of the user's connected calendars.
     *
     * Three answers rather than a boolean: a calendar that once connected and
     * is now failing is a different problem from one that was never set up.
     *
     * @return array{state: string, label: string, detail: string|null}
     */
    protected function calendarStatus(User $user): array
    {
        $accounts = $user->calendarAccounts;

        if ($accounts->isEmpty()) {
            return ['state' => 'none', 'label' => 'Not connected', 'detail' => null];
        }

        $failing = $accounts->first(fn (CalendarAccount $account) => filled($account->sync_error));

        if ($failing !== null) {
            return ['state' => 'error', 'label' => 'Sync error', 'detail' => $failing->sync_error];
        }

        $synced = $accounts->max('last_synced_at');

        return [
            'state' => 'synced',
            'label' => 'Synced',
            'detail' => $synced?->diffForHumans(),
        ];
    }

    /**
     * Get the initials shown in place of an avatar.
     */
    protected function initials(string $name): string
    {
        return collect(explode(' ', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
