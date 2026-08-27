<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\EventType;
use App\Models\Team;
use App\Models\User;
use App\Services\Scheduling\AvailabilityEngine;
use App\Services\Scheduling\BookingPageResolver;
use App\Support\TimeRange;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingPageController extends Controller
{
    public function __construct(
        protected BookingPageResolver $pages,
        protected AvailabilityEngine $availability,
    ) {
        //
    }

    /**
     * Display the event types someone can book.
     */
    public function show(string $page): Response
    {
        $owner = $this->pages->resolve($page);

        abort_if($owner === null, 404);

        $eventTypes = $this->pages->eventTypes($owner);

        return Inertia::render('book/Page', [
            'page' => [
                'slug' => $page,
                'name' => $this->pages->name($owner),
                'welcomeMessage' => $owner->welcome_message,
                'isTeam' => $owner instanceof Team,
                'logoUrl' => $this->pages->logoUrl($owner),
                'websiteUrl' => $this->pages->websiteUrl($owner),
            ],
            'eventTypes' => $eventTypes->map(fn (EventType $eventType) => [
                'slug' => $eventType->slug,
                'name' => $eventType->name,
                'description' => $eventType->description,
                'color' => $eventType->color,
                'durationMinutes' => $eventType->duration_minutes,
                'locationLabel' => $eventType->location_type->label(),
                'url' => route('book.event-type', ['page' => $page, 'eventType' => $eventType->slug]),
            ]),
        ]);
    }

    /**
     * Display the slot picker for a single event type.
     */
    public function eventType(Request $request, string $page, string $eventType): Response
    {
        $owner = $this->pages->resolve($page);

        abort_if($owner === null, 404);

        $type = $this->pages->eventType($owner, $eventType);

        abort_if($type === null, 404);

        $timezone = $this->resolveTimezone($request, $owner);
        $month = $this->resolveMonth($request, $timezone);

        return Inertia::render('book/EventType', [
            'page' => [
                'slug' => $page,
                'name' => $this->pages->name($owner),
                'url' => route('book.page', ['page' => $page]),
                'logoUrl' => $this->pages->logoUrl($owner),
            ],
            'eventType' => $this->toPayload($type),
            'timezone' => $timezone,
            'timezones' => timezone_identifiers_list(),
            'month' => $month->format('Y-m'),
            'reschedule' => $request->string('reschedule')->toString() ?: null,
            'slots' => Inertia::defer(fn () => $this->slotsForMonth($type, $month, $timezone)),
        ]);
    }

    /**
     * Group the bookable slots of a month by local date.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function slotsForMonth(EventType $eventType, CarbonImmutable $month, string $timezone): array
    {
        $window = new TimeRange(
            $month->startOfMonth()->startOfDay()->utc(),
            $month->endOfMonth()->endOfDay()->utc(),
        );

        return $this->availability
            ->slots($eventType, $window)
            ->groupBy(fn ($slot) => $slot->startsAt->setTimezone($timezone)->toDateString())
            ->map(fn ($slots) => $slots->map(fn ($slot) => [
                'startsAt' => $slot->startsAt->toIso8601String(),
                'label' => $slot->startsAt->setTimezone($timezone)->format('g:ia'),
                'seatsRemaining' => $slot->seatsRemaining,
            ])->values()->all())
            ->all();
    }

    /**
     * Work out which month the picker should show.
     */
    protected function resolveMonth(Request $request, string $timezone): CarbonImmutable
    {
        $requested = $request->string('month')->toString();
        $now = CarbonImmutable::now($timezone);

        if ($requested === '' || ! preg_match('/^\d{4}-\d{2}$/', $requested)) {
            return $now->startOfMonth();
        }

        $month = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $requested.'-01 00:00:00', $timezone);

        return $month->lessThan($now->startOfMonth()) ? $now->startOfMonth() : $month;
    }

    /**
     * Work out which timezone to show times in.
     */
    protected function resolveTimezone(Request $request, User|Team $owner): string
    {
        $requested = $request->string('timezone')->toString();

        if ($requested !== '' && in_array($requested, timezone_identifiers_list(), true)) {
            return $requested;
        }

        return $owner->timezone ?: config('scheduling.default_timezone');
    }

    /**
     * Present an event type for the public picker.
     *
     * @return array<string, mixed>
     */
    protected function toPayload(EventType $eventType): array
    {
        $hosts = $eventType->kind->hasHostPool() ? $eventType->hosts : collect([$eventType->owner]);

        return [
            'slug' => $eventType->slug,
            'name' => $eventType->name,
            'description' => $eventType->description,
            'color' => $eventType->color,
            'durationMinutes' => $eventType->duration_minutes,
            'kind' => $eventType->kind->value,
            'locationLabel' => $eventType->location_type->label(),
            'locationDetail' => $eventType->location_type->requiresHostDetail() ? $eventType->location_detail : null,
            'needsInviteePhone' => $eventType->location_type->requiresInviteeInput(),
            'seatsPerSlot' => $eventType->seats(),
            'hostNames' => $hosts->pluck('name')->values(),
            'questions' => $eventType->questions->map(fn ($question) => [
                'id' => $question->id,
                'type' => $question->type->value,
                'label' => $question->label,
                'helpText' => $question->help_text,
                'options' => $question->options ?? [],
                'isRequired' => $question->is_required,
            ])->values(),
        ];
    }
}
