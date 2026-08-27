<?php

namespace App\Services\Calendly;

use App\Enums\BookingStatus;
use App\Enums\EventTypeKind;
use App\Enums\LocationType;
use App\Enums\QuestionType;
use App\Enums\TeamRole;
use App\Models\AvailabilityOverride;
use App\Models\AvailabilityRule;
use App\Models\AvailabilitySchedule;
use App\Models\Booking;
use App\Models\CalendlyImport;
use App\Models\EventType;
use App\Models\Group;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Pull an existing Calendly setup into this app so onboarding does not mean
 * rebuilding every event type and schedule by hand.
 *
 * Everything is written directly through the models rather than through the
 * booking actions, because those actions notify invitees and push to connected
 * calendars. An import must be silent: nobody gets an email about a meeting
 * that was already arranged somewhere else.
 */
class CalendlyImporter
{
    /**
     * Resources this importer knows how to bring over, in dependency order.
     *
     * @var array<int, string>
     */
    public const RESOURCES = ['members', 'groups', 'event_types', 'schedules', 'bookings'];

    /**
     * Counts of what happened, keyed by resource.
     *
     * @var array<string, array{imported: int, updated: int, skipped: int}>
     */
    protected array $tally = [];

    /**
     * Human-readable notes about anything that could not be mapped cleanly.
     *
     * @var array<int, string>
     */
    protected array $warnings = [];

    public function __construct(protected CalendlyClient $client) {}

    /**
     * Run the import into the given organization.
     *
     * @param  array<int, string>  $only  Resources to import; defaults to all.
     * @return array{account: array<string, mixed>, tally: array<string, array<string, int>>, warnings: array<int, string>}
     */
    public function import(Team $team, array $only = self::RESOURCES, bool $dryRun = false, ?string $since = null): array
    {
        $this->tally = [];
        $this->warnings = [];

        $account = $this->client->currentUser();
        $userUri = $account['uri'] ?? null;
        $organizationUri = $account['current_organization'] ?? null;

        if ($userUri === null) {
            $this->warn('Calendly did not return an account for this token; nothing was imported.');

            return $this->result($account);
        }

        if ($organizationUri === null) {
            $this->warn('The token is not attached to an organization; nothing was imported.');

            return $this->result($account);
        }

        $memberships = $this->client->organizationMemberships($organizationUri);

        if (in_array('members', $only, true)) {
            $this->importMembers($team, $memberships, $dryRun);
        }

        if (in_array('groups', $only, true)) {
            $this->importGroups($team, $organizationUri, $dryRun);
        }

        $owner = $this->localUserFor($userUri) ?? $team->owner();

        if (! $owner instanceof User) {
            $this->warn('No local user could be matched to the Calendly account; skipping the rest.');

            return $this->result($account);
        }

        if (in_array('schedules', $only, true)) {
            // Every member has their own working hours, not just the token owner.
            foreach ($memberships as $membership) {
                $person = $membership['user'] ?? [];
                $host = $this->userForEmail($person['email'] ?? null);

                if ($host !== null && filled($person['uri'] ?? null)) {
                    $this->importSchedules($host, (string) $person['uri'], $dryRun);
                }
            }
        }

        if (in_array('event_types', $only, true)) {
            $this->importEventTypes($team, $owner, $organizationUri, $dryRun);
        }

        if (in_array('bookings', $only, true)) {
            $this->importBookings($team, $owner, $organizationUri, $dryRun, $since);
        }

        return $this->result($account);
    }

    /**
     * Match Calendly organization members onto local users.
     */
    /**
     * @param  array<int, array<string, mixed>>  $memberships
     */
    protected function importMembers(Team $team, array $memberships, bool $dryRun): void
    {
        foreach ($memberships as $membership) {
            $person = $membership['user'] ?? [];
            $email = strtolower((string) ($person['email'] ?? ''));
            $uri = (string) ($person['uri'] ?? '');

            if ($email === '' || $uri === '') {
                $this->count('members', 'skipped');

                continue;
            }

            $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($dryRun) {
                $this->count('members', $existing ? 'updated' : 'imported');

                continue;
            }

            /*
             * A new person gets a shell account with an unusable password: they
             * still have to go through the normal invite or reset flow to get in.
             */
            $user = $existing ?? User::create([
                'name' => $person['name'] ?? $email,
                'email' => $email,
                'password' => Str::password(32),
                'timezone' => $person['timezone'] ?? null,
            ]);

            if (! $team->members()->whereKey($user->id)->exists()) {
                $team->members()->attach($user, [
                    'role' => $this->roleFor($membership['role'] ?? 'user')->value,
                ]);
            }

            // Without a current organization the app has nowhere to land them
            // on first sign in, so point anyone unassigned at this one.
            if ($user->current_team_id === null) {
                $user->forceFill(['current_team_id' => $team->id])->save();
            }

            $this->record($uri, 'member', $user, $membership);
            $this->count('members', $existing ? 'updated' : 'imported');
        }
    }

    /**
     * Bring across the weekly hours and date overrides.
     */
    protected function importSchedules(User $owner, string $userUri, bool $dryRun): void
    {
        foreach ($this->client->availabilitySchedules($userUri) as $remote) {
            $uri = (string) ($remote['uri'] ?? '');

            if ($uri === '') {
                $this->count('schedules', 'skipped');

                continue;
            }

            $existing = $this->importedModel($uri, AvailabilitySchedule::class);

            if ($dryRun) {
                $this->count('schedules', $existing ? 'updated' : 'imported');

                continue;
            }

            /** @var AvailabilitySchedule $schedule */
            $schedule = $existing ?? new AvailabilitySchedule;
            $schedule->user_id = $owner->id;
            $schedule->name = $remote['name'] ?? 'Imported schedule';
            $schedule->timezone = $remote['timezone'] ?? $owner->timezone ?? config('scheduling.default_timezone');
            $schedule->is_default = (bool) ($remote['default'] ?? false);
            $schedule->save();

            // Rules are replaced wholesale: Calendly is the source of truth here.
            $schedule->rules()->delete();
            $schedule->overrides()->delete();

            foreach ($remote['rules'] ?? [] as $rule) {
                $this->importScheduleRule($schedule, $rule);
            }

            $this->record($uri, 'schedule', $schedule, $remote);
            $this->count('schedules', $existing ? 'updated' : 'imported');
        }
    }

    /**
     * Turn one Calendly rule into weekly rules or a date override.
     *
     * @param  array<string, mixed>  $rule
     */
    protected function importScheduleRule(AvailabilitySchedule $schedule, array $rule): void
    {
        $intervals = $rule['intervals'] ?? [];

        if (($rule['type'] ?? null) === 'date') {
            $date = $rule['date'] ?? null;

            if ($date === null) {
                return;
            }

            if ($intervals === []) {
                AvailabilityOverride::create([
                    'availability_schedule_id' => $schedule->id,
                    'date' => $date,
                    'is_unavailable' => true,
                ]);

                return;
            }

            foreach ($intervals as $interval) {
                AvailabilityOverride::create([
                    'availability_schedule_id' => $schedule->id,
                    'date' => $date,
                    'starts_at' => $interval['from'] ?? null,
                    'ends_at' => $interval['to'] ?? null,
                    'is_unavailable' => false,
                ]);
            }

            return;
        }

        $day = $this->dayNumberFor($rule['wday'] ?? null);

        if ($day === null) {
            return;
        }

        foreach ($intervals as $interval) {
            AvailabilityRule::create([
                'availability_schedule_id' => $schedule->id,
                'day_of_week' => $day,
                'starts_at' => $interval['from'] ?? null,
                'ends_at' => $interval['to'] ?? null,
            ]);
        }
    }

    /**
     * Bring across the bookable event types.
     */
    protected function importEventTypes(Team $team, User $fallbackOwner, string $organizationUri, bool $dryRun): void
    {
        foreach ($this->client->organizationEventTypes($organizationUri) as $remote) {
            $uri = (string) ($remote['uri'] ?? '');

            if ($uri === '' || ($remote['type'] ?? null) === 'AdhocEventType') {
                // One-off meeting polls have no equivalent here.
                $this->count('event_types', 'skipped');

                continue;
            }

            $existing = $this->importedModel($uri, EventType::class);

            if ($dryRun) {
                $this->count('event_types', $existing ? 'updated' : 'imported');

                continue;
            }

            [$locationType, $locationDetail] = $this->locationFor($remote['locations'] ?? []);

            // An event type belongs to whoever owns it in Calendly, not to
            // whoever happens to hold the API token.
            $owner = $this->userForUri($remote['profile']['owner'] ?? null) ?? $fallbackOwner;

            /** @var EventType $eventType */
            $eventType = $existing ?? new EventType;
            $eventType->team_id = $team->id;
            $eventType->user_id = $owner->id;
            $eventType->availability_schedule_id = $eventType->availability_schedule_id
                ?: $this->defaultScheduleFor($owner)?->id;
            $eventType->kind = $this->kindFor($remote);
            $eventType->name = $remote['name'] ?? 'Imported event type';
            $eventType->slug = $eventType->slug ?: $this->uniqueSlug($team, $remote);
            $eventType->description = $remote['description_plain'] ?? null;
            $eventType->color = $remote['color'] ?? '#006BFF';
            $eventType->duration_minutes = (int) ($remote['duration'] ?? 30);
            $eventType->location_type = $locationType;
            $eventType->location_detail = $locationDetail;
            $eventType->is_active = (bool) ($remote['active'] ?? true);
            $eventType->is_hidden = (bool) ($remote['secret'] ?? false);
            $eventType->save();

            $this->importQuestions($eventType, $remote['custom_questions'] ?? []);
            $this->importEventTypeHosts($eventType, $uri, $remote);

            $this->record($uri, 'event_type', $eventType, $remote);
            $this->count('event_types', $existing ? 'updated' : 'imported');
        }
    }

    /**
     * Bring across the meetings already on the calendar, for the whole
     * organization rather than one person.
     *
     * The host comes off the event membership, so no extra call is needed for
     * it. The invitee's name and email are only available per event, which is
     * one request each — the slow part of a large import.
     */
    protected function importBookings(
        Team $team,
        User $fallbackHost,
        string $organizationUri,
        bool $dryRun,
        ?string $since = null,
    ): void {
        $filters = $since === null ? [] : ['min_start_time' => $since];

        foreach ($this->client->organizationScheduledEvents($organizationUri, $filters) as $remote) {
            $uri = (string) ($remote['uri'] ?? '');
            $eventTypeUri = (string) ($remote['event_type'] ?? '');

            if ($uri === '') {
                $this->count('bookings', 'skipped');

                continue;
            }

            $eventType = $this->importedModel($eventTypeUri, EventType::class);

            if (! $eventType instanceof EventType) {
                $this->count('bookings', 'skipped');

                continue;
            }

            $existing = $this->importedModel($uri, Booking::class);

            if ($dryRun) {
                $this->count('bookings', $existing ? 'updated' : 'imported');

                continue;
            }

            $membership = $remote['event_memberships'][0] ?? [];
            $host = $this->userForEmail($membership['user_email'] ?? null) ?? $fallbackHost;

            $invitee = $this->client->eventInvitees($uri)[0] ?? [];

            /** @var Booking $booking */
            $booking = $existing ?? new Booking;
            $booking->event_type_id = $eventType->id;
            $booking->team_id = $team->id;
            $booking->user_id = $host->id;
            $booking->status = ($remote['status'] ?? 'active') === 'active'
                ? BookingStatus::Confirmed
                : BookingStatus::Canceled;
            $booking->starts_at = Carbon::parse($remote['start_time']);
            $booking->ends_at = Carbon::parse($remote['end_time']);
            $booking->name = $invitee['name'] ?? ($remote['name'] ?? 'Imported invitee');
            $booking->email = $invitee['email'] ?? '';
            $booking->invitee_timezone = $invitee['timezone'] ?? $host->timezone ?? config('scheduling.default_timezone');
            [$locationType, $locationDetail] = $this->bookingLocationFor($remote['location'] ?? [], $eventType);
            $booking->location_type = $locationType;
            $booking->location_detail = $locationDetail;
            $booking->meeting_url = $remote['location']['join_url'] ?? null;
            $booking->save();

            // The host pool on the booking is what the meetings list shows.
            $hostIds = collect($remote['event_memberships'] ?? [])
                ->map(fn (array $m) => $this->userForEmail($m['user_email'] ?? null)?->id)
                ->filter()
                ->unique()
                ->values();

            if ($hostIds->isNotEmpty()) {
                $booking->hosts()->sync($hostIds->all());
            }

            $this->importGuests($booking, $remote['event_guests'] ?? []);

            $this->record($uri, 'booking', $booking, $remote);
            $this->count('bookings', $existing ? 'updated' : 'imported');
        }
    }

    /**
     * Store the extra guests copied on a meeting.
     *
     * @param  array<int, array<string, mixed>>  $guests
     */
    protected function importGuests(Booking $booking, array $guests): void
    {
        $booking->guests()->delete();

        foreach ($guests as $guest) {
            $email = $guest['email'] ?? null;

            if (filled($email)) {
                $booking->guests()->create(['email' => $email]);
            }
        }
    }

    /**
     * Work out where a booked meeting happens.
     *
     * @param  array<string, mixed>  $location
     * @return array{0: LocationType, 1: string|null}
     */
    protected function bookingLocationFor(array $location, EventType $eventType): array
    {
        return match ($location['type'] ?? null) {
            'google_conference' => [LocationType::GoogleMeet, null],
            'microsoft_teams_conference' => [LocationType::MicrosoftTeams, null],
            'physical' => [LocationType::InPerson, $location['location'] ?? null],
            'outbound_call' => [LocationType::Phone, $location['location'] ?? null],
            'inbound_call' => [LocationType::InviteePhone, $location['location'] ?? null],
            // Fall back to however the event type is configured.
            default => [$eventType->location_type, $eventType->location_detail],
        };
    }

    /**
     * Map a Calendly event type onto one of our kinds.
     *
     * @param  array<string, mixed>  $remote
     */
    protected function kindFor(array $remote): EventTypeKind
    {
        return match ($remote['pooling_type'] ?? null) {
            'round_robin' => EventTypeKind::RoundRobin,
            'collective' => EventTypeKind::Collective,
            default => ($remote['kind'] ?? null) === 'group'
                ? EventTypeKind::Group
                : EventTypeKind::OneOnOne,
        };
    }

    /**
     * Map Calendly's location list onto our single location type.
     *
     * @param  array<int, array<string, mixed>>  $locations
     * @return array{0: LocationType, 1: string|null}
     */
    protected function locationFor(array $locations): array
    {
        $first = $locations[0] ?? [];

        return match ($first['kind'] ?? null) {
            'google_conference' => [LocationType::GoogleMeet, null],
            'microsoft_teams_conference' => [LocationType::MicrosoftTeams, null],
            'physical' => [LocationType::InPerson, $first['location'] ?? null],
            'outbound_call' => [LocationType::Phone, $first['location'] ?? null],
            'inbound_call' => [LocationType::InviteePhone, null],
            default => [LocationType::CustomLink, $first['location'] ?? null],
        };
    }

    /**
     * Map a Calendly membership role onto ours.
     */
    protected function roleFor(string $role): TeamRole
    {
        return match ($role) {
            'owner' => TeamRole::Owner,
            'admin' => TeamRole::Admin,
            default => TeamRole::Member,
        };
    }

    /**
     * Turn a Calendly weekday name into our 0-Sunday number.
     */
    protected function dayNumberFor(?string $wday): ?int
    {
        return match ($wday) {
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            default => null,
        };
    }

    /**
     * Build a slug that does not collide with an existing event type.
     *
     * @param  array<string, mixed>  $remote
     */
    protected function uniqueSlug(Team $team, array $remote): string
    {
        $base = Str::slug($remote['slug'] ?? $remote['name'] ?? 'event');
        $slug = $base;
        $suffix = 2;

        while (EventType::query()->where('team_id', $team->id)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Find the local record a Calendly URI was previously imported into.
     *
     * @param  class-string<Model>  $type
     */
    protected function importedModel(string $uri, string $type): ?Model
    {
        if ($uri === '') {
            return null;
        }

        $record = CalendlyImport::query()
            ->where('calendly_uri', $uri)
            ->where('importable_type', $type)
            ->first();

        return $record?->importable;
    }

    /**
     * Find the local user behind a Calendly user URI.
     */
    protected function localUserFor(string $uri): ?User
    {
        $model = $this->importedModel($uri, User::class);

        return $model instanceof User ? $model : null;
    }

    /**
     * Remember that a Calendly resource maps to a local record.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function record(string $uri, string $resourceType, Model $model, array $payload): void
    {
        CalendlyImport::updateOrCreate(
            ['calendly_uri' => $uri],
            [
                'resource_type' => $resourceType,
                'importable_type' => $model::class,
                'importable_id' => $model->getKey(),
                'payload' => $payload,
                'imported_at' => now(),
            ],
        );
    }

    /**
     * Tally one outcome.
     */
    protected function count(string $resource, string $outcome): void
    {
        $this->tally[$resource] ??= ['imported' => 0, 'updated' => 0, 'skipped' => 0];
        $this->tally[$resource][$outcome]++;
    }

    /**
     * Note something the operator should look at.
     */
    protected function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * Shape the return value.
     *
     * @param  array<string, mixed>  $account
     * @return array{account: array<string, mixed>, tally: array<string, array<string, int>>, warnings: array<int, string>}
     */
    protected function result(array $account): array
    {
        return [
            'account' => $account,
            'tally' => $this->tally,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * Bring across the booking questions asked on an event type.
     *
     * Calendly is the source of truth, so the set is replaced rather than
     * merged — a question deleted there should not linger here.
     *
     * @param  array<int, array<string, mixed>>  $questions
     */
    protected function importQuestions(EventType $eventType, array $questions): void
    {
        $eventType->questions()->delete();

        $position = 0;

        foreach ($questions as $question) {
            if (($question['enabled'] ?? true) === false) {
                continue;
            }

            $choices = array_values(array_filter($question['answer_choices'] ?? []));

            $eventType->questions()->create([
                'type' => $this->questionTypeFor($question['type'] ?? null, $choices)->value,
                'label' => $question['name'] ?? 'Question',
                'options' => $choices === [] ? null : $choices,
                'is_required' => (bool) ($question['required'] ?? false),
                'position' => $position++,
            ]);
        }
    }

    /**
     * Map a Calendly question type onto ours.
     *
     * @param  array<int, string>  $choices
     */
    protected function questionTypeFor(?string $type, array $choices): QuestionType
    {
        return match ($type) {
            'phone_number' => QuestionType::Phone,
            'multi_select' => QuestionType::MultiSelect,
            'single_select' => QuestionType::Select,
            // Calendly's "text" is a multi-line box; "string" is a single line.
            'text' => QuestionType::Textarea,
            'string' => QuestionType::Text,
            default => $choices === [] ? QuestionType::Text : QuestionType::Select,
        };
    }

    /**
     * Attach the host pool for a round robin or collective event type.
     *
     * Calendly keeps these on a separate endpoint, so it is one extra call per
     * pooled event type. Priority follows the order Calendly lists them in,
     * which is the order this app then offers them in.
     *
     * @param  array<string, mixed>  $remote
     */
    protected function importEventTypeHosts(EventType $eventType, string $uri, array $remote): void
    {
        if (($remote['pooling_type'] ?? null) === null) {
            return;
        }

        $payload = [];
        $position = 0;

        foreach ($this->client->eventTypeHosts($uri) as $membership) {
            $email = strtolower((string) ($membership['member']['email'] ?? ''));

            if ($email === '') {
                continue;
            }

            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($user === null) {
                $this->warn('Host '.$email.' is not a member here, so "'.$eventType->name.'" skipped them.');

                continue;
            }

            $payload[$user->id] = ['priority' => $position++];
        }

        if ($payload !== []) {
            $eventType->hosts()->sync($payload);
        }
    }

    /**
     * Bring across the organization's groups as teams.
     *
     * Calendly's public API returns a group's name but has no endpoint for its
     * membership, so each one arrives empty and has to be filled in here. The
     * names are still worth importing — they are the structure the account was
     * organised around.
     */
    protected function importGroups(Team $team, string $organizationUri, bool $dryRun): void
    {
        $imported = [];

        foreach ($this->client->groups($organizationUri) as $remote) {
            $uri = (string) ($remote['uri'] ?? '');
            $name = (string) ($remote['name'] ?? '');

            if ($uri === '' || $name === '') {
                $this->count('groups', 'skipped');

                continue;
            }

            $existing = $this->importedModel($uri, Group::class);

            if ($dryRun) {
                $this->count('groups', $existing ? 'updated' : 'imported');

                continue;
            }

            /** @var Group $group */
            $group = $existing ?? new Group;
            $group->team_id = $team->id;
            $group->name = $name;
            $group->save();

            $this->record($uri, 'group', $group, $remote);
            $this->count('groups', $existing ? 'updated' : 'imported');

            if ($group->members()->count() === 0) {
                $imported[] = $name;
            }
        }

        if ($imported !== []) {
            $this->warn(
                'Calendly does not expose group membership, so '.
                implode(' and ', $imported).
                ' came across with no members — add them under Teams.'
            );
        }
    }

    /**
     * Find the local user behind a Calendly email address.
     */
    protected function userForEmail(?string $email): ?User
    {
        if (! filled($email)) {
            return null;
        }

        return User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
    }

    /**
     * Find the local user behind a Calendly user URI.
     */
    protected function userForUri(?string $uri): ?User
    {
        return filled($uri) ? $this->localUserFor((string) $uri) : null;
    }

    /**
     * Get the schedule a new event type should default to.
     */
    protected function defaultScheduleFor(User $owner): ?AvailabilitySchedule
    {
        return AvailabilitySchedule::query()
            ->where('user_id', $owner->id)
            ->orderByDesc('is_default')
            ->first();
    }
}
