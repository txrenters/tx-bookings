<?php

use App\Enums\BookingStatus;
use App\Enums\EventTypeKind;
use App\Enums\LocationType;
use App\Models\AvailabilitySchedule;
use App\Models\Booking;
use App\Models\CalendlyImport;
use App\Models\EventType;
use App\Models\Group;
use App\Models\Team;
use App\Models\User;
use App\Services\Calendly\CalendlyClient;
use App\Services\Calendly\CalendlyImporter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

const CALENDLY_USER = 'https://api.calendly.com/users/U1';
const CALENDLY_ORG = 'https://api.calendly.com/organizations/O1';
const CALENDLY_EVENT_TYPE = 'https://api.calendly.com/event_types/ET1';
const CALENDLY_EVENT = 'https://api.calendly.com/scheduled_events/EV1';

/**
 * Build the organization the import writes into.
 *
 * @return array{0: User, 1: Team}
 */
function calendlyTarget(): array
{
    $user = User::factory()->create(['email' => 'owner@texasrenters.com']);
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => 'admin']);

    return [$user, $team];
}

/**
 * Fake the whole Calendly surface the importer touches.
 *
 * @param  array<string, mixed>  $overrides
 */
function fakeCalendly(array $overrides = []): void
{
    $responses = [
        'api.calendly.com/users/me' => Http::response([
            'resource' => [
                'uri' => CALENDLY_USER,
                'name' => 'Texas Renters',
                'email' => 'owner@texasrenters.com',
                'timezone' => 'America/Chicago',
                'current_organization' => CALENDLY_ORG,
            ],
        ]),
        'api.calendly.com/organization_memberships*' => Http::response([
            'collection' => [[
                'role' => 'owner',
                'user' => [
                    'uri' => CALENDLY_USER,
                    'name' => 'Texas Renters',
                    'email' => 'owner@texasrenters.com',
                    'timezone' => 'America/Chicago',
                ],
            ]],
            'pagination' => ['next_page_token' => null],
        ]),
        'api.calendly.com/user_availability_schedules*' => Http::response([
            'collection' => [[
                'uri' => 'https://api.calendly.com/user_availability_schedules/S1',
                'name' => 'Working hours',
                'timezone' => 'America/Chicago',
                'default' => true,
                'rules' => [
                    ['type' => 'wday', 'wday' => 'monday', 'intervals' => [['from' => '09:00', 'to' => '17:00']]],
                    ['type' => 'wday', 'wday' => 'sunday', 'intervals' => []],
                    ['type' => 'date', 'date' => '2026-12-25', 'intervals' => []],
                ],
            ]],
            'pagination' => ['next_page_token' => null],
        ]),
        'api.calendly.com/event_types*' => Http::response([
            'collection' => [[
                'uri' => CALENDLY_EVENT_TYPE,
                'name' => 'Property Tour',
                'slug' => 'property-tour',
                'active' => true,
                'secret' => false,
                'duration' => 45,
                'color' => '#8CBF52',
                'description_plain' => 'Walk the unit with a leasing agent.',
                'kind' => 'solo',
                'pooling_type' => null,
                'type' => 'StandardEventType',
                'locations' => [['kind' => 'physical', 'location' => '123 Main St']],
            ]],
            'pagination' => ['next_page_token' => null],
        ]),
        'api.calendly.com/groups*' => Http::response([
            'collection' => [
                ['uri' => 'https://api.calendly.com/groups/G1', 'name' => 'Leasing'],
                ['uri' => 'https://api.calendly.com/groups/G2', 'name' => 'Maintenance'],
            ],
            'pagination' => ['next_page_token' => null],
        ]),
        'api.calendly.com/event_type_memberships*' => Http::response([
            'collection' => [],
            'pagination' => ['next_page_token' => null],
        ]),
        'api.calendly.com/scheduled_events/EV1/invitees*' => Http::response([
            'collection' => [[
                'name' => 'Dana Reyes',
                'email' => 'dana@example.com',
                'timezone' => 'America/Chicago',
            ]],
            'pagination' => ['next_page_token' => null],
        ]),
        'api.calendly.com/scheduled_events*' => Http::response([
            'collection' => [[
                'uri' => CALENDLY_EVENT,
                'name' => 'Property Tour',
                'status' => 'active',
                'start_time' => '2026-09-01T15:00:00.000000Z',
                'end_time' => '2026-09-01T15:45:00.000000Z',
                'event_type' => CALENDLY_EVENT_TYPE,
                'location' => ['join_url' => null],
                'event_memberships' => [['user_email' => 'owner@texasrenters.com']],
                'event_guests' => [],
            ]],
            'pagination' => ['next_page_token' => null],
        ]),
    ];

    // The catch-all goes last so nothing in these tests can reach the network.
    Http::fake([...$responses, ...$overrides, '*' => Http::response([], 418)]);
}

/**
 * Run a real import against the fake.
 *
 * @param  array<int, string>  $only
 * @return array<string, mixed>
 */
function runImport(Team $team, array $only = CalendlyImporter::RESOURCES, bool $dryRun = false): array
{
    config()->set('services.calendly.token', 'test-token');
    config()->set('services.calendly.base_url', 'https://api.calendly.com');

    return (new CalendlyImporter(new CalendlyClient))->import($team, $only, $dryRun);
}

test('event types come across with their mapped kind and location', function () {
    fakeCalendly();
    [, $team] = calendlyTarget();

    runImport($team, ['members', 'schedules', 'event_types']);

    $eventType = EventType::query()->where('team_id', $team->id)->sole();

    expect($eventType->name)->toBe('Property Tour')
        ->and($eventType->slug)->toBe('property-tour')
        ->and($eventType->duration_minutes)->toBe(45)
        ->and($eventType->kind)->toBe(EventTypeKind::OneOnOne)
        ->and($eventType->location_type)->toBe(LocationType::InPerson)
        ->and($eventType->location_detail)->toBe('123 Main St')
        ->and($eventType->is_active)->toBeTrue();
});

test('weekly hours and date overrides come across', function () {
    fakeCalendly();
    [$user, $team] = calendlyTarget();

    runImport($team, ['members', 'schedules']);

    $schedule = AvailabilitySchedule::query()->where('user_id', $user->id)->sole();

    expect($schedule->name)->toBe('Working hours')
        ->and($schedule->timezone)->toBe('America/Chicago')
        ->and($schedule->is_default)->toBeTrue();

    // Monday 09:00-17:00 only; Sunday has no intervals so produces no rule.
    $rules = $schedule->rules()->get();

    expect($rules)->toHaveCount(1)
        ->and($rules->first()->day_of_week)->toBe(1);

    $override = $schedule->overrides()->sole();

    expect($override->is_unavailable)->toBeTrue();
});

test('scheduled events come across as bookings', function () {
    fakeCalendly();
    [, $team] = calendlyTarget();

    runImport($team);

    $booking = Booking::query()->where('team_id', $team->id)->sole();

    expect($booking->name)->toBe('Dana Reyes')
        ->and($booking->email)->toBe('dana@example.com')
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->starts_at->toIso8601String())->toBe('2026-09-01T15:00:00+00:00');
});

test('importing bookings never notifies the invitee', function () {
    Notification::fake();
    fakeCalendly();
    [, $team] = calendlyTarget();

    runImport($team);

    Notification::assertNothingSent();
});

test('running the import twice does not duplicate anything', function () {
    fakeCalendly();
    [, $team] = calendlyTarget();

    runImport($team);
    $result = runImport($team);

    expect(EventType::query()->where('team_id', $team->id)->count())->toBe(1)
        ->and(Booking::query()->where('team_id', $team->id)->count())->toBe(1)
        ->and(AvailabilitySchedule::query()->count())->toBe(1)
        ->and($result['tally']['event_types']['updated'])->toBe(1)
        ->and($result['tally']['event_types']['imported'])->toBe(0);
});

test('a dry run writes nothing but still reports counts', function () {
    fakeCalendly();
    [, $team] = calendlyTarget();

    $result = runImport($team, CalendlyImporter::RESOURCES, dryRun: true);

    expect(EventType::query()->count())->toBe(0)
        ->and(Booking::query()->count())->toBe(0)
        ->and(CalendlyImport::query()->count())->toBe(0)
        ->and($result['tally']['event_types']['imported'])->toBe(1);
});

test('an existing member is matched by email rather than duplicated', function () {
    fakeCalendly();
    [$user, $team] = calendlyTarget();

    runImport($team, ['members']);

    expect(User::query()->where('email', 'owner@texasrenters.com')->count())->toBe(1)
        ->and(User::query()->count())->toBe(1)
        ->and($team->members()->whereKey($user->id)->exists())->toBeTrue();
});

test('collective event types map to the right kind', function () {
    fakeCalendly([
        'api.calendly.com/event_types*' => Http::response([
            'collection' => [[
                'uri' => CALENDLY_EVENT_TYPE,
                'name' => 'Panel',
                'slug' => 'panel',
                'duration' => 30,
                'pooling_type' => 'collective',
                'type' => 'StandardEventType',
                'locations' => [['kind' => 'google_conference']],
            ]],
            'pagination' => ['next_page_token' => null],
        ]),
    ]);
    [, $team] = calendlyTarget();

    runImport($team, ['members', 'schedules', 'event_types']);

    $eventType = EventType::query()->sole();

    expect($eventType->kind)->toBe(EventTypeKind::Collective)
        ->and($eventType->location_type)->toBe(LocationType::GoogleMeet);
});

test('a rejected token is reported clearly', function () {
    Http::fake(['api.calendly.com/*' => Http::response([], 401)]);
    [, $team] = calendlyTarget();

    runImport($team);
})->throws(RuntimeException::class, 'Calendly rejected the API token');

test('the command refuses to run without a token', function () {
    config()->set('services.calendly.token', null);

    $this->artisan('calendly:import')
        ->expectsOutputToContain('No Calendly API token configured')
        ->assertExitCode(1);
});

test('calendly groups come across as teams, empty because the api hides members', function () {
    fakeCalendly();
    [, $team] = calendlyTarget();

    $result = runImport($team, ['members', 'groups']);

    $groups = Group::query()->where('team_id', $team->id)->orderBy('name')->get();

    expect($groups->pluck('name')->all())->toBe(['Leasing', 'Maintenance'])
        ->and($groups->first()->members()->count())->toBe(0);

    // The gap must be reported rather than passing as a complete import.
    expect(implode(' ', $result['warnings']))->toContain('does not expose group membership');
});

test('re-importing groups does not duplicate them', function () {
    fakeCalendly();
    [, $team] = calendlyTarget();

    runImport($team, ['members', 'groups']);
    $result = runImport($team, ['members', 'groups']);

    expect(Group::query()->where('team_id', $team->id)->count())->toBe(2)
        ->and($result['tally']['groups']['imported'])->toBe(0)
        ->and($result['tally']['groups']['updated'])->toBe(2);
});
