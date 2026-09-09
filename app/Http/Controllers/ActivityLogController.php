<?php

namespace App\Http\Controllers;

use App\Enums\TeamPermission;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    /**
     * The event families offered as filters.
     *
     * @var array<int, array{value: string, label: string}>
     */
    protected array $kinds = [
        ['value' => 'all', 'label' => 'Everything'],
        ['value' => 'booking', 'label' => 'Bookings'],
        ['value' => 'event_type', 'label' => 'Event types'],
        ['value' => 'member', 'label' => 'Members'],
        ['value' => 'invitation', 'label' => 'Invitations'],
        ['value' => 'organization', 'label' => 'Organization'],
    ];

    /**
     * The number of entries loaded per page.
     */
    protected int $perPage = 25;

    /**
     * Show the organization's audit trail.
     */
    public function index(Request $request, Team $current_team): Response
    {
        $user = $request->user();
        $timezone = $user->timezone ?: config('scheduling.default_timezone');

        $kind = $request->string('kind', 'all')->toString();

        if (! collect($this->kinds)->pluck('value')->contains($kind)) {
            $kind = 'all';
        }

        // An admin reads the organization's trail; a member reads their own
        // part of it -- what they did, what was done to them, and the meetings
        // they host.
        $seesEveryone = $user->hasTeamPermission($current_team, TeamPermission::ManageTeamBookings);

        $entries = ActivityLog::query()
            ->where('team_id', $current_team->id)
            ->when(! $seesEveryone, fn ($query) => $query->where(
                fn (Builder $mine) => $mine
                    ->where('user_id', $user->id)
                    ->orWhere(fn (Builder $about) => $about
                        ->where('subject_type', User::class)
                        ->where('subject_id', $user->id))
                    ->orWhereHasMorph(
                        'subject',
                        Booking::class,
                        fn (Builder $booking) => $booking
                            ->where('user_id', $user->id)
                            ->orWhereHas('hosts', fn (Builder $hosts) => $hosts->whereKey($user->id)),
                    ),
            ))
            ->when($kind !== 'all', fn ($query) => $query->ofKind($kind))
            ->with('user:id,name')
            ->latest()
            ->paginate($this->perPage)
            ->withQueryString()
            ->through(fn (ActivityLog $entry) => [
                'id' => $entry->id,
                'event' => $entry->event,
                'kind' => str($entry->event)->before('.')->toString(),
                'description' => $entry->description,
                // The stored name survives a rename or a deleted account.
                'actorName' => $entry->user->name ?? $entry->actor_name,
                'isSystem' => $entry->user_id === null && $entry->actor_name === null,
                'properties' => $entry->properties,
                'at' => $entry->created_at->setTimezone($timezone)->isoFormat('D MMM YYYY, h:mm a'),
                'atIso' => $entry->created_at->toIso8601String(),
            ]);

        return Inertia::render('Activity', [
            'entries' => $entries,
            'kinds' => $this->kinds,
            'kind' => $kind,
            'seesEveryone' => $seesEveryone,
        ]);
    }
}
