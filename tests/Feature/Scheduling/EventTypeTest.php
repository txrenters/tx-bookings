<?php

use App\Enums\EventTypeKind;
use App\Enums\TeamRole;
use App\Models\AvailabilitySchedule;
use App\Models\EventType;
use App\Models\Group;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

function eventTypePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Discovery call',
        'slug' => 'discovery-call',
        'description' => 'A first chat.',
        'kind' => EventTypeKind::OneOnOne->value,
        'color' => '#0f766e',
        'duration_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'minimum_notice_minutes' => 240,
        'seats_per_slot' => 1,
        'date_range_type' => 'rolling_days',
        'rolling_days' => 60,
        'location_type' => 'custom_link',
        'location_detail' => 'https://example.com/room',
        'is_active' => true,
        'is_hidden' => false,
    ], $overrides);
}

test('the index lists the teams event types', function () {
    EventType::factory()->ownedBy($this->user)->create(['name' => 'Discovery call']);

    $this->actingAs($this->user)
        ->get(route('scheduling.index', ['current_team' => $this->team->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('scheduling/event-types/Index')
            ->has('eventTypes', 1)
            ->where('eventTypes.0.name', 'Discovery call'));
});

test('the index carries everything the quick create panel needs', function () {
    AvailabilitySchedule::factory()->for($this->user)->weekdays()->create(['name' => 'Working hours']);

    $this->actingAs($this->user)
        ->get(route('scheduling.index', ['current_team' => $this->team->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('kinds', 4)
            ->has('locationTypes')
            ->has('teamMembers', 1)
            ->where('currentUser.name', $this->user->name)
            ->where('schedules.0.name', 'Working hours')
            ->where('schedules.0.summary', 'Weekdays, 9 am - 5 pm'));
});

test('a schedule with no hours reports no availability', function () {
    AvailabilitySchedule::factory()->for($this->user)->create(['name' => 'Empty']);

    $this->actingAs($this->user)
        ->get(route('scheduling.index', ['current_team' => $this->team->slug]))
        ->assertInertia(fn ($page) => $page->where('schedules.0.summary', null));
});

test('an event type can be created from the quick create panel', function () {
    $this->actingAs($this->user)
        ->post(route('scheduling.store', ['current_team' => $this->team->slug]), eventTypePayload())
        ->assertRedirect(route('scheduling.index', ['current_team' => $this->team->slug]));

    $eventType = EventType::first();

    expect($eventType->name)->toBe('Discovery call')
        ->and($eventType->user_id)->toBe($this->user->id)
        ->and($eventType->team_id)->toBe($this->team->id)
        ->and($eventType->kind)->toBe(EventTypeKind::OneOnOne);
});

test('the slug must be unique within the team', function () {
    EventType::factory()->ownedBy($this->user)->create(['slug' => 'discovery-call']);

    $this->actingAs($this->user)
        ->post(route('scheduling.store', ['current_team' => $this->team->slug]), eventTypePayload())
        ->assertSessionHasErrors('slug');
});

test('reserved slugs are rejected', function () {
    $this->actingAs($this->user)
        ->post(route('scheduling.store', ['current_team' => $this->team->slug]), eventTypePayload(['slug' => 'dashboard']))
        ->assertSessionHasErrors('slug');
});

test('a location that needs a detail cannot be saved without one', function () {
    $this->actingAs($this->user)
        ->post(route('scheduling.store', ['current_team' => $this->team->slug]), eventTypePayload([
            'location_type' => 'in_person',
            'location_detail' => '',
        ]))
        ->assertSessionHasErrors('location_detail');
});

test('a round robin needs at least one host', function () {
    $this->actingAs($this->user)
        ->post(route('scheduling.store', ['current_team' => $this->team->slug]), eventTypePayload([
            'kind' => EventTypeKind::RoundRobin->value,
            'host_ids' => [],
        ]))
        ->assertSessionHasErrors('host_ids');
});

test('a round robin stores its host pool in priority order', function () {
    $team = Team::factory()->create();
    $second = User::factory()->create();

    $team->members()->attach($this->user, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($second, ['role' => TeamRole::Member->value]);
    $this->user->switchTeam($team);

    $this->actingAs($this->user)
        ->post(route('scheduling.store', ['current_team' => $team->slug]), eventTypePayload([
            'kind' => EventTypeKind::RoundRobin->value,
            'host_ids' => [$second->id, $this->user->id],
        ]))
        ->assertSessionHasNoErrors();

    $hosts = EventType::first()->hosts;

    expect($hosts->pluck('id')->all())->toBe([$second->id, $this->user->id])
        ->and($hosts->first()->pivot->priority)->toBe(0);
});

test('custom questions are saved with the event type', function () {
    $this->actingAs($this->user)
        ->post(route('scheduling.store', ['current_team' => $this->team->slug]), eventTypePayload([
            'questions' => [
                ['type' => 'text', 'label' => 'Company', 'is_required' => true],
                ['type' => 'select', 'label' => 'Size', 'options' => ['1-10', '11-50'], 'is_required' => false],
            ],
        ]))
        ->assertSessionHasNoErrors();

    $questions = EventType::first()->questions;

    expect($questions)->toHaveCount(2)
        ->and($questions->first()->label)->toBe('Company')
        ->and($questions->first()->is_required)->toBeTrue()
        ->and($questions->last()->options)->toBe(['1-10', '11-50']);
});

test('updating replaces the question set', function () {
    $eventType = EventType::factory()->ownedBy($this->user)->create(['slug' => 'discovery-call']);
    $kept = $eventType->questions()->create(['type' => 'text', 'label' => 'Company', 'position' => 0]);
    $eventType->questions()->create(['type' => 'text', 'label' => 'Budget', 'position' => 1]);

    $this->actingAs($this->user)
        ->patch(route('scheduling.update', [
            'current_team' => $this->team->slug,
            'event_type' => $eventType->slug,
        ]), eventTypePayload([
            'questions' => [
                ['id' => $kept->id, 'type' => 'text', 'label' => 'Company name', 'is_required' => true],
            ],
        ]))
        ->assertSessionHasNoErrors();

    $questions = $eventType->fresh()->questions;

    expect($questions)->toHaveCount(1)
        ->and($questions->first()->id)->toBe($kept->id)
        ->and($questions->first()->label)->toBe('Company name');
});

test('a member of another team cannot edit an event type', function () {
    $eventType = EventType::factory()->ownedBy($this->user)->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('scheduling.edit', [
            'current_team' => $this->team->slug,
            'event_type' => $eventType->slug,
        ]))
        ->assertForbidden();
});

test('an event type slug resolves within the current team only', function () {
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($this->user, ['role' => TeamRole::Owner->value]);

    EventType::factory()->create([
        'team_id' => $otherTeam->id,
        'user_id' => $this->user->id,
        'slug' => 'shared-slug',
    ]);

    $this->actingAs($this->user)
        ->get(route('scheduling.edit', [
            'current_team' => $this->team->slug,
            'event_type' => 'shared-slug',
        ]))
        ->assertNotFound();
});

test('an event type can be deleted', function () {
    $eventType = EventType::factory()->ownedBy($this->user)->create();

    $this->actingAs($this->user)
        ->delete(route('scheduling.destroy', [
            'current_team' => $this->team->slug,
            'event_type' => $eventType->slug,
        ]))
        ->assertRedirect(route('scheduling.index', ['current_team' => $this->team->slug]));

    expect($eventType->fresh()->trashed())->toBeTrue();
});

test('an admin can create a one-on-one on behalf of another member', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();

    $team->members()->attach($this->user, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $this->user->switchTeam($team);

    $this->actingAs($this->user)
        ->post(route('scheduling.store', ['current_team' => $team->slug]), eventTypePayload([
            'user_id' => $member->id,
        ]))
        ->assertSessionHasNoErrors();

    expect(EventType::first()->user_id)->toBe($member->id);
});

test('a plain member cannot hand an event type to someone else', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();
    $other = User::factory()->create();

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $team->members()->attach($other, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $this->actingAs($member)
        ->post(route('scheduling.store', ['current_team' => $team->slug]), eventTypePayload([
            'user_id' => $other->id,
        ]))
        ->assertSessionHasNoErrors();

    // The request is accepted, but ownership stays with the creator.
    expect(EventType::first()->user_id)->toBe($member->id);
});

test('an event type cannot be handed to someone outside the team', function () {
    $stranger = User::factory()->create();

    $this->actingAs($this->user)
        ->post(route('scheduling.store', ['current_team' => $this->team->slug]), eventTypePayload([
            'user_id' => $stranger->id,
        ]))
        ->assertSessionHasErrors('user_id');
});

test('an admin can reassign an existing event type to another member', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();

    $team->members()->attach($this->user, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $this->user->switchTeam($team);

    $eventType = EventType::factory()->create([
        'team_id' => $team->id,
        'user_id' => $this->user->id,
        'slug' => 'discovery-call',
    ]);

    $this->actingAs($this->user)
        ->patch(route('scheduling.update', [
            'current_team' => $team->slug,
            'event_type' => $eventType->slug,
        ]), eventTypePayload(['user_id' => $member->id]))
        ->assertSessionHasNoErrors();

    expect($eventType->fresh()->user_id)->toBe($member->id);
});

test('the form knows whether the viewer may reassign ownership', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $this->actingAs($member)
        ->get(route('scheduling.index', ['current_team' => $team->slug]))
        ->assertInertia(fn ($page) => $page->where('canAssignOwner', false));

    $this->actingAs($this->user)
        ->get(route('scheduling.index', ['current_team' => $this->team->slug]))
        ->assertInertia(fn ($page) => $page->where('canAssignOwner', true));
});

test('each event type carries an availability line and a shared flag', function () {
    AvailabilitySchedule::factory()->for($this->user)->weekdays('10:30:00', '16:00:00')->create();

    EventType::factory()->ownedBy($this->user)->create([
        'name' => 'Discovery call',
        'location_type' => 'in_person',
        'location_detail' => null,
    ]);

    $this->actingAs($this->user)
        ->get(route('scheduling.index', ['current_team' => $this->team->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('eventTypes.0.availabilitySummary', 'Weekdays, 10:30 am - 4 pm')
            ->where('eventTypes.0.locationLabel', 'No location set')
            ->where('eventTypes.0.isShared', false)
            ->where('eventTypes.0.ownerName', $this->user->name));
});

test('a pooled event type whose hosts differ reports varying hours', function () {
    $team = Team::factory()->create();
    $second = User::factory()->create();

    $team->members()->attach($this->user, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($second, ['role' => TeamRole::Member->value]);
    $this->user->switchTeam($team);

    AvailabilitySchedule::factory()->for($this->user)->weekdays('09:00:00', '17:00:00')->create();
    AvailabilitySchedule::factory()->for($second)->weekdays('11:00:00', '15:00:00')->create();

    $eventType = EventType::factory()->roundRobin()->create([
        'team_id' => $team->id,
        'user_id' => $this->user->id,
    ]);
    $eventType->hosts()->attach([$this->user->id, $second->id]);

    $this->actingAs($this->user)
        ->get(route('scheduling.index', ['current_team' => $team->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('eventTypes.0.availabilitySummary', 'Hours vary by host')
            ->where('eventTypes.0.isShared', true));
});

test('an event type with no hours says so', function () {
    EventType::factory()->ownedBy($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('scheduling.index', ['current_team' => $this->team->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('eventTypes.0.availabilitySummary', 'No available days or times'));
});

test('the scope picker offers primary, group and user options', function () {
    $group = Group::factory()->create(['team_id' => $this->team->id, 'name' => 'Maintenance Team']);

    $this->actingAs($this->user)
        ->get(route('scheduling.index', ['current_team' => $this->team->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('scope', 'all')
            ->has('scopeOptions.primary', 2)
            ->where('scopeOptions.groups.0.label', 'Maintenance Team')
            ->where('scopeOptions.users.0.label', $this->user->name));
});

test('scoping to a group narrows the list to that groups event types', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();

    $team->members()->attach($this->user, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $this->user->switchTeam($team);

    $group = Group::factory()->create(['team_id' => $team->id]);
    $group->members()->attach($member);

    EventType::factory()->create(['team_id' => $team->id, 'user_id' => $member->id, 'name' => 'Theirs']);
    EventType::factory()->create(['team_id' => $team->id, 'user_id' => $this->user->id, 'name' => 'Mine']);

    $this->actingAs($this->user)
        ->get(route('scheduling.index', ['current_team' => $team->slug, 'scope' => "group:{$group->id}"]))
        ->assertInertia(fn ($page) => $page
            ->has('eventTypes', 1)
            ->where('eventTypes.0.name', 'Theirs'));
});

test('an unknown scope falls back to showing everything', function () {
    EventType::factory()->ownedBy($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('scheduling.index', ['current_team' => $this->team->slug, 'scope' => 'group:999']))
        ->assertInertia(fn ($page) => $page
            ->where('scope', 'all')
            ->has('eventTypes', 1));
});

test('team event types are listed under Shared with their host avatars', function () {
    $team = Team::factory()->create();
    $second = User::factory()->create(['name' => 'Kim Alvarez']);

    $team->members()->attach($this->user, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($second, ['role' => TeamRole::Member->value]);
    $this->user->switchTeam($team);

    $eventType = EventType::factory()->roundRobin()->create([
        'team_id' => $team->id,
        'user_id' => $this->user->id,
    ]);
    $eventType->hosts()->attach([$this->user->id, $second->id]);

    $this->actingAs($this->user)
        ->get(route('scheduling.index', ['current_team' => $team->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('eventTypes.0.ownerName', 'Shared')
            ->has('eventTypes.0.hosts', 2)
            ->where('eventTypes.0.hosts.1.initial', 'K'));
});

test('a solo event type is listed under its owner', function () {
    EventType::factory()->ownedBy($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('scheduling.index', ['current_team' => $this->team->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('eventTypes.0.ownerName', $this->user->name)
            ->has('eventTypes.0.hosts', 1));
});

test('the create menu describes each kind the way the picker reads', function () {
    $this->actingAs($this->user)
        ->get(route('scheduling.index', ['current_team' => $this->team->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('kinds.0.label', 'One-on-one')
            ->where('kinds.0.flow', '1 host → 1 invitee')
            ->where('kinds.0.description', 'Good for coffee chats, 1:1 interviews, etc.')
            ->where('kinds.2.label', 'Round robin')
            ->where('kinds.2.flow', 'Rotating hosts → 1 invitee'));
});

test('a group event type keeps its seats', function () {
    $this->actingAs($this->user)
        ->post(route('scheduling.store', ['current_team' => $this->team->slug]), eventTypePayload([
            'kind' => EventTypeKind::Group->value,
            'seats_per_slot' => 5,
        ]))
        ->assertSessionHasNoErrors();

    $eventType = EventType::first();

    expect($eventType->kind)->toBe(EventTypeKind::Group)
        ->and($eventType->seats_per_slot)->toBe(5)
        ->and($eventType->seats())->toBe(5);
});

test('a one-on-one always has a single seat', function () {
    $this->actingAs($this->user)
        ->post(route('scheduling.store', ['current_team' => $this->team->slug]), eventTypePayload([
            'seats_per_slot' => 5,
        ]))
        ->assertSessionHasNoErrors();

    // Seats only mean something for group events.
    expect(EventType::first()->seats())->toBe(1);
});
