<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\EventType;
use App\Models\Team;
use App\Models\TeamInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The number of upcoming meetings listed on the dashboard.
     */
    protected int $upcomingLimit = 5;

    public function __invoke(Request $request, Team $current_team): Response
    {
        $user = $request->user();
        $email = strtolower($user->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'greetingName' => str($user->name)->before(' ')->toString(),
            'bookingUrl' => $user->booking_slug
                ? route('book.page', ['page' => $user->booking_slug])
                : null,
            // Both need aggregate queries, so the shell paints first and these
            // stream in behind their skeletons.
            'stats' => Inertia::defer(fn () => $this->stats($user, $current_team)),
            'upcoming' => Inertia::defer(fn () => $this->upcoming($user, $current_team)),
        ]);
    }

    /**
     * Build the headline counters.
     *
     * @return array{upcoming: int, thisWeek: int, eventTypes: int}
     */
    protected function stats(mixed $user, Team $team): array
    {
        $now = CarbonImmutable::now();

        return [
            'upcoming' => $this->hosted($user, $team)->active()->upcoming()->count(),
            'thisWeek' => $this->hosted($user, $team)
                ->active()
                ->whereBetween('starts_at', [$now->startOfWeek(), $now->endOfWeek()])
                ->count(),
            'eventTypes' => EventType::query()
                ->where('team_id', $team->id)
                ->where('is_active', true)
                ->count(),
        ];
    }

    /**
     * Get the next few meetings the user is hosting.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function upcoming(mixed $user, Team $team): array
    {
        $timezone = $user->timezone ?: config('scheduling.default_timezone');

        return $this->hosted($user, $team)
            ->active()
            ->upcoming()
            ->with(['eventType:id,name,color'])
            ->orderBy('starts_at')
            ->limit($this->upcomingLimit)
            ->get()
            ->map(fn (Booking $booking) => [
                'uid' => $booking->uid,
                'name' => $booking->name,
                'eventTypeName' => $booking->eventType?->name,
                'color' => $booking->eventType?->color,
                'startsAt' => $booking->starts_at->toIso8601String(),
                'dayLabel' => $booking->starts_at->setTimezone($timezone)->isoFormat('ddd D MMM'),
                'timeLabel' => $booking->starts_at->setTimezone($timezone)->isoFormat('h:mm a'),
                'isToday' => $booking->starts_at->setTimezone($timezone)->isToday(),
            ])
            ->all();
    }

    /**
     * Scope bookings to the ones this user hosts on the given team.
     *
     * A booking's host pool lives on the pivot, so a user can host a meeting
     * they do not own — check both.
     *
     * @return Builder<Booking>
     */
    protected function hosted(mixed $user, Team $team): Builder
    {
        return Booking::query()
            ->where('team_id', $team->id)
            ->where(fn ($query) => $query
                ->where('user_id', $user->id)
                ->orWhereHas('hosts', fn ($hosts) => $hosts->where('users.id', $user->id)));
    }
}
