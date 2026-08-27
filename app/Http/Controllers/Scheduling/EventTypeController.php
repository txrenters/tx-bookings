<?php

namespace App\Http\Controllers\Scheduling;

use App\Actions\Scheduling\SaveEventType;
use App\Enums\DateRangeType;
use App\Enums\EventTypeKind;
use App\Enums\LocationType;
use App\Enums\QuestionType;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\SaveEventTypeRequest;
use App\Models\EventType;
use App\Models\Group;
use App\Models\Team;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use App\Services\Scheduling\EventTypeAvailabilitySummary;
use App\Services\Scheduling\ScopeFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EventTypeController extends Controller
{
    /**
     * Display the team's event types.
     */
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', EventType::class);

        $user = $request->user();
        $scopes = app(ScopeFilter::class);
        $scope = $request->string('scope', 'all')->toString();

        if (! $scopes->validValues($current_team)->contains($scope)) {
            $scope = 'all';
        }

        $eventTypes = $scopes->applyToHosts(
            $current_team->eventTypes()->getQuery(),
            $scopes->userIds($scope, $current_team, $user),
            'hosts',
        )
            ->with(['owner:id,name,booking_slug', 'hosts:id,name', 'group.members:id,name', 'team:id,name,slug'])
            ->withCount(['bookings' => fn ($query) => $query->active()->upcoming()])
            ->orderBy('name')
            ->get();

        $summaries = app(EventTypeAvailabilitySummary::class)->forMany($eventTypes);

        return Inertia::render('scheduling/event-types/Index', [
            ...$this->formOptions($request, $current_team),
            'eventTypes' => $eventTypes->map(fn (EventType $eventType) => $this->toListItem(
                $eventType,
                $summaries->get($eventType->id, 'No available days or times'),
            )),
            'canCreate' => $user->can('create', [EventType::class, $current_team]),
            'scope' => $scope,
            'scopeOptions' => $scopes->options($current_team, $user),
        ]);
    }

    /**
     * Store a newly created event type.
     */
    public function store(SaveEventTypeRequest $request, Team $current_team, SaveEventType $saveEventType): RedirectResponse
    {
        Gate::authorize('create', [EventType::class, $current_team]);

        $eventType = $saveEventType->handle(
            $this->resolveOwner($request, $current_team),
            $current_team,
            $request->validated(),
        );

        app(ActivityLogger::class)->record(
            $current_team,
            'event_type.created',
            'Created the event type "'.$eventType->name.'"',
            $eventType,
            ['kind' => $eventType->kind->value, 'durationMinutes' => $eventType->duration_minutes],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event type created.')]);

        // The quick create panel captures everything needed, so stay on the list.
        return to_route('scheduling.index', ['current_team' => $current_team->slug]);
    }

    /**
     * Show the form for editing an event type.
     */
    public function edit(Request $request, Team $current_team, EventType $event_type): Response
    {
        Gate::authorize('update', $event_type);

        $event_type->load(['hosts:id,name', 'questions', 'group:id,name']);

        return Inertia::render('scheduling/event-types/Edit', [
            ...$this->formOptions($request, $current_team),
            'eventType' => $this->toFormPayload($event_type),
            'publicUrl' => $this->publicUrl($event_type),
        ]);
    }

    /**
     * Update an event type.
     */
    public function update(SaveEventTypeRequest $request, Team $current_team, EventType $event_type, SaveEventType $saveEventType): RedirectResponse
    {
        Gate::authorize('update', $event_type);

        $eventType = $saveEventType->handle(
            $this->resolveOwner($request, $current_team, $event_type),
            $current_team,
            $request->validated(),
            $event_type,
        );

        app(ActivityLogger::class)->record(
            $current_team,
            'event_type.updated',
            'Updated the event type "'.$eventType->name.'"',
            $eventType,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event type updated.')]);

        return to_route('scheduling.edit', ['current_team' => $current_team->slug, 'event_type' => $eventType->slug]);
    }

    /**
     * Delete an event type.
     */
    public function destroy(Team $current_team, EventType $event_type): RedirectResponse
    {
        Gate::authorize('delete', $event_type);

        $name = $event_type->name;

        $event_type->delete();

        app(ActivityLogger::class)->record(
            $current_team,
            'event_type.deleted',
            'Deleted the event type "'.$name.'"',
            $event_type,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event type deleted.')]);

        return to_route('scheduling.index', ['current_team' => $current_team->slug]);
    }

    /**
     * Work out who should own the event type.
     *
     * Members always own what they create. Someone who manages the team's
     * bookings may hand ownership to another member, which is how an admin
     * sets up a one-on-one on somebody else's behalf.
     */
    protected function resolveOwner(SaveEventTypeRequest $request, Team $team, ?EventType $eventType = null): User
    {
        $actor = $request->user();
        $requestedId = $request->validated('user_id');
        $current = $eventType !== null ? $eventType->owner : $actor;

        if ($requestedId === null || (int) $requestedId === $current->id) {
            return $current;
        }

        if (! $actor->hasTeamPermission($team, TeamPermission::ManageTeamBookings)) {
            return $current;
        }

        return $team->members()->whereKey($requestedId)->first() ?? $current;
    }

    /**
     * Get the reference data the create and edit forms need.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(Request $request, Team $team): array
    {
        $user = $request->user();

        return [
            'kinds' => EventTypeKind::options(),
            'locationTypes' => LocationType::options(),
            'questionTypes' => array_map(
                fn (QuestionType $type) => ['value' => $type->value, 'label' => $type->label(), 'hasOptions' => $type->hasOptions()],
                QuestionType::cases(),
            ),
            'dateRangeTypes' => array_map(
                fn (DateRangeType $type) => ['value' => $type->value, 'label' => $type->label()],
                DateRangeType::cases(),
            ),
            'schedules' => $user->availabilitySchedules()
                ->with('rules')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(fn ($schedule) => [
                    'id' => $schedule->id,
                    'name' => $schedule->name,
                    'timezone' => $schedule->timezone,
                    'isDefault' => $schedule->is_default,
                    'summary' => $schedule->summary(),
                ]),
            'teamMembers' => $team->members()->get(['users.id', 'users.name', 'users.email'])
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                ]),
            'isPersonalTeam' => $team->is_personal,
            'currentUser' => ['id' => $user->id, 'name' => $user->name],
            'groups' => $team->groups()->with('members:id,name')->orderBy('name')->get()
                ->map(fn (Group $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'memberNames' => $group->members->pluck('name')->values(),
                ]),
            // Only someone who manages the team's bookings may hand an event
            // type to a different member.
            'canAssignOwner' => $user->hasTeamPermission($team, TeamPermission::ManageTeamBookings),
        ];
    }

    /**
     * Present an event type for the listing.
     *
     * @return array<string, mixed>
     */
    protected function toListItem(EventType $eventType, string $availabilitySummary): array
    {
        return [
            'id' => $eventType->id,
            'name' => $eventType->name,
            'slug' => $eventType->slug,
            'description' => $eventType->description,
            'color' => $eventType->color,
            'kind' => $eventType->kind->value,
            'kindLabel' => $eventType->kind->label(),
            'durationMinutes' => $eventType->duration_minutes,
            'availabilitySummary' => $availabilitySummary,
            'isShared' => $eventType->kind->hasHostPool(),
            'locationLabel' => $this->locationLabel($eventType),
            'isActive' => $eventType->is_active,
            'isHidden' => $eventType->is_hidden,
            'upcomingBookings' => $eventType->bookings_count,
            'ownerName' => $this->ownerName($eventType),
            'ownerLandingUrl' => $this->ownerLandingUrl($eventType),
            'groupName' => $eventType->group?->name,
            'hosts' => $this->hostBadges($eventType),
            'publicUrl' => $this->publicUrl($eventType),
        ];
    }

    /**
     * Present an event type for the edit form.
     *
     * @return array<string, mixed>
     */
    protected function toFormPayload(EventType $eventType): array
    {
        return [
            'id' => $eventType->id,
            'name' => $eventType->name,
            'slug' => $eventType->slug,
            'description' => $eventType->description,
            'color' => $eventType->color,
            'kind' => $eventType->kind->value,
            'durationMinutes' => $eventType->duration_minutes,
            'slotIntervalMinutes' => $eventType->slot_interval_minutes,
            'bufferBeforeMinutes' => $eventType->buffer_before_minutes,
            'bufferAfterMinutes' => $eventType->buffer_after_minutes,
            'minimumNoticeMinutes' => $eventType->minimum_notice_minutes,
            'dailyBookingLimit' => $eventType->daily_booking_limit,
            'seatsPerSlot' => $eventType->seats_per_slot,
            'dateRangeType' => $eventType->date_range_type->value,
            'rollingDays' => $eventType->rolling_days,
            'rangeStartsOn' => $eventType->range_starts_on?->toDateString(),
            'rangeEndsOn' => $eventType->range_ends_on?->toDateString(),
            'locationType' => $eventType->location_type->value,
            'locationDetail' => $eventType->location_detail,
            'availabilityScheduleId' => $eventType->availability_schedule_id,
            'isActive' => $eventType->is_active,
            'isHidden' => $eventType->is_hidden,
            'groupId' => $eventType->group_id,
            'ownerId' => $eventType->user_id,
            'hostIds' => $eventType->hosts->pluck('id'),
            'questions' => $eventType->questions->map(fn ($question) => [
                'id' => $question->id,
                'type' => $question->type->value,
                'label' => $question->label,
                'help_text' => $question->help_text,
                'options' => $question->options ?? [],
                'is_required' => $question->is_required,
            ]),
        ];
    }

    /**
     * Get the hosts shown as avatars against a row.
     *
     * @return Collection<int, array{id: int, name: string, initial: uppercase-string}>
     */
    protected function hostBadges(EventType $eventType): Collection
    {
        return $eventType->hostPool()
            ->map(fn (User $host) => [
                'id' => $host->id,
                'name' => $host->name,
                'initial' => strtoupper(mb_substr(trim($host->name), 0, 1)),
            ])
            ->values();
    }

    /**
     * Get the name the event type is listed under.
     *
     * Pooled event types belong to the team; everything else to its owner.
     */
    protected function ownerName(EventType $eventType): string
    {
        return $eventType->kind->hasHostPool() ? 'Shared' : $eventType->owner->name;
    }

    /**
     * Get the public page the event type is listed under.
     */
    protected function ownerLandingUrl(EventType $eventType): string
    {
        return route('book.page', [
            'page' => $eventType->kind->hasHostPool()
                ? $eventType->team->slug
                : ($eventType->owner->booking_slug ?? $eventType->team->slug),
        ]);
    }

    /**
     * Describe where the meeting happens, or say when nothing is set.
     */
    protected function locationLabel(EventType $eventType): string
    {
        if ($eventType->location_type->requiresHostDetail() && blank($eventType->location_detail)) {
            return 'No location set';
        }

        return $eventType->location_type->label();
    }

    /**
     * Build the public booking URL for an event type.
     */
    protected function publicUrl(EventType $eventType): string
    {
        $page = $eventType->kind->hasHostPool()
            ? $eventType->team->slug
            : ($eventType->owner->booking_slug ?? $eventType->team->slug);

        return route('book.event-type', ['page' => $page, 'eventType' => $eventType->slug]);
    }
}
