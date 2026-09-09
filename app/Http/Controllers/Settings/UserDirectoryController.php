<?php

namespace App\Http\Controllers\Settings;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
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
            ->with(['teams:id,name,slug,is_personal'])
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
            'organizations' => $user->teams
                ->map(fn (Team $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'isPersonal' => (bool) $team->is_personal,
                    // teams() carries a plain pivot, so the role arrives as a
                    // string rather than the cast Membership enum.
                    'roleLabel' => TeamRole::from($team->getRelation('pivot')->role)->label(),
                ])
                ->values(),
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
