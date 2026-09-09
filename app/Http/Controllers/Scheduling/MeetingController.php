<?php

namespace App\Http\Controllers\Scheduling;

use App\Actions\Bookings\ApproveBooking;
use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\DeclineBooking;
use App\Enums\BookingStatus;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\EventType;
use App\Models\Team;
use App\Models\User;
use App\Services\Scheduling\ScopeFilter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    /**
     * The date ranges offered as chips above the list.
     *
     * @var array<int, array{value: string, label: string}>
     */
    protected array $ranges = [
        ['value' => 'today', 'label' => 'Today'],
        ['value' => 'upcoming', 'label' => 'Upcoming'],
        ['value' => 'this_week', 'label' => 'This week'],
        ['value' => 'last_week', 'label' => 'Last week'],
        ['value' => 'past', 'label' => 'Past'],
    ];

    /**
     * The number of meetings loaded per page.
     */
    protected int $perPage = 20;

    /**
     * Display the meetings for the current team.
     */
    public function index(Request $request, Team $current_team, ScopeFilter $scopes): Response
    {
        $user = $request->user();
        $timezone = $user->timezone ?: config('scheduling.default_timezone');

        $defaultScope = $scopes->defaultScope($current_team, $user);
        $scope = $request->string('scope', $defaultScope)->toString();

        if (! $scopes->validValues($current_team, $user)->contains($scope)) {
            $scope = $defaultScope;
        }

        $range = $request->string('filter', 'upcoming')->toString();

        if (! collect($this->ranges)->pluck('value')->contains($range)) {
            $range = 'upcoming';
        }

        $status = $request->string('status', 'active')->toString();

        if (! in_array($status, ['active', 'pending', 'canceled'], true)) {
            $status = 'active';
        }

        $search = $request->string('search')->toString();
        $eventTypeId = $request->integer('event_type');
        $page = max(1, $request->integer('page', 1));

        $query = $this->baseQuery($current_team, $user, $scopes, $scope);

        $this->applyRange($query, $range, $timezone, $status);
        $this->applySearch($query, $search);

        if ($eventTypeId > 0) {
            $query->where('event_type_id', $eventTypeId);
        }

        $total = (clone $query)->count();

        $meetings = $query
            ->with(['eventType:id,name,color', 'hosts:id,name', 'host:id,name', 'guests', 'answers'])
            ->forPage($page, $this->perPage)
            ->get();

        return Inertia::render('scheduling/meetings/Index', [
            'meetings' => Inertia::merge(
                fn () => $meetings->map(fn (Booking $booking) => $this->toPayload($booking, $timezone))->all(),
            ),
            'total' => $total,
            'page' => $page,
            'hasMore' => $total > $page * $this->perPage,
            'filter' => $range,
            'ranges' => $this->ranges,
            'status' => $status,
            'scope' => $scope,
            'scopeOptions' => $scopes->options($current_team, $user),
            'search' => $search,
            'eventTypeId' => $eventTypeId > 0 ? $eventTypeId : null,
            'eventTypeOptions' => $current_team->eventTypes()->orderBy('name')->get(['id', 'name'])
                ->map(fn (EventType $eventType) => ['value' => $eventType->id, 'label' => $eventType->name]),
            'viewerTimezone' => $timezone,
        ]);
    }

    /**
     * Cancel a meeting as one of its hosts.
     */
    public function destroy(Request $request, Team $current_team, Booking $booking, CancelBooking $cancelBooking): RedirectResponse
    {
        Gate::authorize('cancel', $booking);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $cancelBooking->handle($booking, $validated['reason'] ?? null, canceledBy: 'host');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Meeting canceled.')]);

        return back();
    }

    /**
     * Build the query of meetings this viewer is allowed to see.
     *
     * @return Builder<Booking>
     */
    protected function baseQuery(Team $team, User $user, ScopeFilter $scopes, string $scope): Builder
    {
        $visibleTo = $user->can('viewAny', Booking::class)
            && $user->hasTeamPermission($team, TeamPermission::ManageTeamBookings)
            ? null
            : [$user->id];

        $query = Booking::query()->where('team_id', $team->id);

        // Members only ever see their own meetings; the scope picker narrows
        // further within whatever they are allowed to see.
        $scopes->applyToHosts($query, $visibleTo, 'hosts');
        $scopes->applyToHosts($query, $scopes->userIds($scope, $team, $user), 'hosts');

        return $query;
    }

    /**
     * Narrow the query to the chosen date range.
     *
     * @param  Builder<Booking>  $query
     */
    protected function applyRange(Builder $query, string $range, string $timezone, string $status): void
    {
        $now = CarbonImmutable::now($timezone);

        // Cancelled meetings are a status, not a date range, so they can be
        // combined with any of the chips above the list. Active includes
        // pending requests so hosts cannot miss them.
        match ($status) {
            'canceled' => $query->whereIn('status', [BookingStatus::Canceled, BookingStatus::Rescheduled]),
            'pending' => $query->where('status', BookingStatus::Pending),
            default => $query->active(),
        };

        match ($range) {
            'today' => $query
                ->whereBetween('starts_at', [$now->startOfDay()->utc(), $now->endOfDay()->utc()])
                ->orderBy('starts_at'),
            'this_week' => $query
                ->whereBetween('starts_at', [$now->startOfWeek()->utc(), $now->endOfWeek()->utc()])
                ->orderBy('starts_at'),
            'last_week' => $query
                ->whereBetween('starts_at', [
                    $now->subWeek()->startOfWeek()->utc(),
                    $now->subWeek()->endOfWeek()->utc(),
                ])
                ->orderBy('starts_at'),
            'past' => $query->where('starts_at', '<', $now)->orderByDesc('starts_at'),
            default => $query->where('starts_at', '>=', $now)->orderBy('starts_at'),
        };
    }

    /**
     * Narrow the query by a free text search.
     *
     * @param  Builder<Booking>  $query
     */
    protected function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $query) use ($search) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhereHas('eventType', fn (Builder $eventType) => $eventType->where('name', 'like', "%{$search}%"));
        });
    }

    /**
     * Present a meeting for the listing.
     *
     * @return array<string, mixed>
     */
    protected function toPayload(Booking $booking, string $timezone): array
    {
        $localStart = $booking->starts_at->setTimezone($timezone);
        $hosts = $booking->hosts->isNotEmpty() ? $booking->hosts : collect([$booking->host])->filter();

        return [
            'uid' => $booking->uid,
            'status' => $booking->status->value,
            'statusLabel' => $booking->status->label(),
            'startsAt' => $booking->starts_at->toIso8601String(),
            'dateKey' => $localStart->toDateString(),
            'dateLabel' => $this->dateLabel($localStart, $timezone),
            'dayLabel' => $localStart->format('D'),
            'dayDateLabel' => $localStart->format('j M'),
            'isToday' => $localStart->isSameDay(CarbonImmutable::now($timezone)),
            'fullDateLabel' => $localStart->format('l, j F'),
            'timeLabel' => $localStart->format('g:i a').' - '
                .$booking->ends_at->setTimezone($timezone)->format('g:i a'),
            'timeWithZone' => $localStart->format('g:i a').' - '
                .$booking->ends_at->setTimezone($timezone)->format('g:i a')
                .' ('.$localStart->format('T').')',
            'inviteeName' => $booking->name,
            'inviteeEmail' => $booking->email,
            'inviteeTimezone' => $booking->invitee_timezone,
            'notes' => $booking->notes,
            'hostNotes' => $booking->host_notes,
            'inviteeInitials' => $this->initials($booking->name),
            'inviteePhone' => $booking->location_type->requiresInviteeInput()
                ? $booking->location_detail
                : null,
            'rescheduleUrl' => route('booking.reschedule', ['booking' => $booking->uid]),
            'meetingUrl' => $booking->meeting_url,
            'locationDetail' => $booking->location_detail,
            'locationLabel' => $booking->location_type->label(),
            'cancellationReason' => $booking->cancellation_reason,
            'eventTypeName' => $booking->eventType->name,
            'color' => $booking->eventType->color,
            'hostNames' => $hosts->map(fn (User $host) => $host->name)->values(),
            'guests' => $booking->guests->map(fn ($guest) => $guest->email)->values(),
            'answers' => $booking->answers->map(fn ($answer) => [
                'label' => $answer->label,
                'answer' => $answer->answer,
            ])->values(),
            'canCancel' => $booking->isChangeable(),
            'canApprove' => $booking->status === BookingStatus::Pending
                && $booking->starts_at->isFuture()
                && Gate::allows('approve', $booking),
        ];
    }

    /**
     * Confirm a pending booking request as one of its hosts.
     */
    public function approve(Request $request, Team $current_team, Booking $booking, ApproveBooking $approveBooking): RedirectResponse
    {
        Gate::authorize('approve', $booking);

        $approveBooking->handle($booking, $request->user());

        $booking->refresh()->status === BookingStatus::Confirmed
            ? Inertia::flash('toast', ['type' => 'success', 'message' => __('Meeting confirmed.')])
            : Inertia::flash('toast', ['type' => 'error', 'message' => __('This booking is no longer awaiting approval.')]);

        return back();
    }

    /**
     * Turn down a pending booking request as one of its hosts.
     */
    public function decline(Request $request, Team $current_team, Booking $booking, DeclineBooking $declineBooking): RedirectResponse
    {
        Gate::authorize('cancel', $booking);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $declineBooking->handle($booking, $request->user(), $validated['reason'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request declined.')]);

        return back();
    }

    /**
     * Save a host's private notes against a meeting.
     */
    public function updateNotes(Request $request, Team $current_team, Booking $booking): RedirectResponse
    {
        Gate::authorize('view', $booking);

        $validated = $request->validate([
            'host_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $booking->update(['host_notes' => $validated['host_notes'] ?? null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notes saved.')]);

        return back();
    }

    /**
     * Get someone's initials for the invitee avatar.
     */
    protected function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }

    /**
     * Get the heading a meeting is listed under.
     */
    protected function dateLabel(CarbonInterface $localStart, string $timezone): string
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $date = CarbonImmutable::parse($localStart)->startOfDay();

        return match (true) {
            $date->equalTo($today) => 'Today',
            $date->equalTo($today->addDay()) => 'Tomorrow',
            $date->equalTo($today->subDay()) => 'Yesterday',
            default => $localStart->format('l, j F Y'),
        };
    }
}
