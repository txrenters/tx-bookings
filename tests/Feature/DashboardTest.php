<?php

use App\Enums\TeamRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Booking;
use App\Models\EventType;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Resolve one of the dashboard's deferred props via a partial reload.
 */
function dashboardDeferredProps(TestCase $test, string $props): TestResponse
{
    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Data' => $props,
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Version' => (new HandleInertiaRequests)->version(request()),
    ])->get(route('dashboard'));
}

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

test('dashboard includes pending invitations for the authenticated user', function () {
    $owner = User::factory()->create(['name' => 'Taylor Otwell']);
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create(['name' => 'Laravel Team']);

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('pendingInvitations', 1)
        ->where('pendingInvitations.0.code', $invitation->code)
        ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
        ->where('pendingInvitations.0.team.name', 'Laravel Team')
        ->where('pendingInvitations.0.team.slug', $team->slug)
        ->missing('pendingInvitations.0.teamName'),
    );
});

test('dashboard does not include accepted invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    TeamInvitation::factory()->accepted()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('pendingInvitations', 0),
    );
});

test('dashboard excludes expired invitations without deleting them', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('dashboard upcoming meetings expose schedule details', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-01 08:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'America/Chicago']);
    $eventType = EventType::factory()->ownedBy($user)->create(['name' => 'Interview']);

    Booking::factory()->create([
        'event_type_id' => $eventType->id,
        'starts_at' => CarbonImmutable::parse('2026-09-03 15:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-09-03 15:45:00', 'UTC'),
        'meeting_url' => 'https://meet.example.com/abc',
    ]);

    $upcoming = dashboardDeferredProps($this->actingAs($user), 'upcoming')
        ->assertOk()
        ->json('props.upcoming');

    expect($upcoming)->toHaveCount(1)
        ->and($upcoming[0]['timeLabel'])->toBe('10:00 am')
        ->and($upcoming[0]['endTimeLabel'])->toBe('10:45 am')
        ->and($upcoming[0]['durationMinutes'])->toBe(45)
        ->and($upcoming[0]['status'])->toBe('confirmed')
        ->and($upcoming[0]['statusLabel'])->toBe('Confirmed')
        ->and($upcoming[0]['meetingUrl'])->toBe('https://meet.example.com/abc');
});

test('a pending booking appears in the upcoming list with its status', function () {
    $user = User::factory()->create();
    $eventType = EventType::factory()->ownedBy($user)->create();

    Booking::factory()->pending()->create(['event_type_id' => $eventType->id]);

    $upcoming = dashboardDeferredProps($this->actingAs($user), 'upcoming')
        ->assertOk()
        ->json('props.upcoming');

    expect($upcoming)->toHaveCount(1)
        ->and($upcoming[0]['status'])->toBe('pending')
        ->and($upcoming[0]['statusLabel'])->toBe('Pending');
});

test('the dashboard lists at most eight upcoming meetings in start order', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-01 08:00:00', 'UTC'));

    $user = User::factory()->create();
    $eventType = EventType::factory()->ownedBy($user)->create();

    foreach (range(1, 9) as $day) {
        Booking::factory()->create([
            'event_type_id' => $eventType->id,
            'starts_at' => CarbonImmutable::parse('2026-09-01 12:00:00', 'UTC')->addDays($day),
            'ends_at' => CarbonImmutable::parse('2026-09-01 12:30:00', 'UTC')->addDays($day),
        ]);
    }

    $upcoming = dashboardDeferredProps($this->actingAs($user), 'upcoming')
        ->assertOk()
        ->json('props.upcoming');

    expect($upcoming)->toHaveCount(8)
        ->and($upcoming[0]['startsAt'])->toBe('2026-09-02T12:00:00+00:00');
});

test('canceled bookings do not appear in the upcoming list', function () {
    $user = User::factory()->create();
    $eventType = EventType::factory()->ownedBy($user)->create();

    Booking::factory()->canceled()->create(['event_type_id' => $eventType->id]);

    $upcoming = dashboardDeferredProps($this->actingAs($user), 'upcoming')
        ->assertOk()
        ->json('props.upcoming');

    expect($upcoming)->toHaveCount(0);
});

test('dashboard stats count upcoming, this week, and active event types', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-01 08:00:00', 'UTC'));

    $user = User::factory()->create();
    $eventType = EventType::factory()->ownedBy($user)->create();

    // Wednesday this week plus one the following week: both upcoming, one this week.
    Booking::factory()->create([
        'event_type_id' => $eventType->id,
        'starts_at' => CarbonImmutable::parse('2026-09-02 15:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-09-02 15:30:00', 'UTC'),
    ]);
    Booking::factory()->create([
        'event_type_id' => $eventType->id,
        'starts_at' => CarbonImmutable::parse('2026-09-09 15:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-09-09 15:30:00', 'UTC'),
    ]);

    $stats = dashboardDeferredProps($this->actingAs($user), 'stats')
        ->assertOk()
        ->json('props.stats');

    expect($stats)->toBe(['upcoming' => 2, 'thisWeek' => 1, 'eventTypes' => 1]);
});

test('dashboard does not include or delete other users invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'someone@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});
