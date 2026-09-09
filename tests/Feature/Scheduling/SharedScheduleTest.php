<?php

use App\Enums\EventTypeKind;
use App\Enums\TeamRole;
use App\Models\AvailabilitySchedule;
use App\Models\EventType;
use App\Models\Group;
use App\Models\Team;
use App\Models\User;
use App\Services\Scheduling\AvailabilityEngine;
use App\Support\TimeRange;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-31 08:00:00', 'UTC'));

    $this->team = Team::factory()->create();
    $this->admin = User::factory()->create(['timezone' => 'UTC']);
    $this->member = User::factory()->create(['timezone' => 'UTC']);

    // Detached from the organizations the factory gives them, so the roles
    // under test are the only ones they hold.
    $this->admin->teams()->detach();
    $this->member->teams()->detach();
    $this->team->members()->attach($this->admin, ['role' => TeamRole::Admin->value]);
    $this->team->members()->attach($this->member, ['role' => TeamRole::Member->value]);
    $this->admin->switchTeam($this->team);
    $this->member->switchTeam($this->team);
});

/** Hours the organization keeps, weekdays only. */
function sharedHours(Team $team, string $startsAt = '09:00:00', string $endsAt = '12:00:00'): AvailabilitySchedule
{
    $schedule = $team->availabilitySchedules()->create([
        'name' => 'Leasing hours',
        'timezone' => 'UTC',
    ]);

    foreach (range(1, 5) as $day) {
        $schedule->rules()->create([
            'day_of_week' => $day,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    return $schedule;
}

/** Personal hours, wider than the shared ones, on Wednesday. */
function personalHours(User $user, string $startsAt = '08:00:00', string $endsAt = '18:00:00'): AvailabilitySchedule
{
    $schedule = $user->availabilitySchedules()->create([
        'name' => 'Working hours',
        'timezone' => 'UTC',
        'is_default' => true,
    ]);

    $schedule->rules()->create([
        'day_of_week' => 3,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ]);

    return $schedule;
}

/** A window covering a single Wednesday. */
function wednesdayWindow(): TimeRange
{
    $day = CarbonImmutable::parse('2026-09-02', 'UTC');

    return new TimeRange($day->startOfDay(), $day->endOfDay());
}

test('an admin creates hours the organization shares', function () {
    $this->actingAs($this->admin)
        ->post(route('availability.store', ['current_team' => $this->team->slug]), [
            'name' => 'Leasing hours',
            'timezone' => 'UTC',
            'is_shared' => true,
            'rules' => [['day_of_week' => 3, 'starts_at' => '09:00', 'ends_at' => '15:00']],
        ])
        ->assertRedirect();

    $schedule = AvailabilitySchedule::query()->where('name', 'Leasing hours')->sole();

    expect($schedule->isShared())->toBeTrue()
        ->and($schedule->team_id)->toBe($this->team->id)
        ->and($schedule->user_id)->toBeNull();
});

test('a member cannot create or edit the organizations hours', function () {
    $this->actingAs($this->member)
        ->post(route('availability.store', ['current_team' => $this->team->slug]), [
            'name' => 'Sneaky hours',
            'timezone' => 'UTC',
            'is_shared' => true,
            'rules' => [],
        ])
        ->assertForbidden();

    $schedule = sharedHours($this->team);

    $this->actingAs($this->member)
        ->patch(route('availability.update', [
            'current_team' => $this->team->slug,
            'availability' => $schedule->id,
        ]), ['name' => 'Rewritten', 'timezone' => 'UTC', 'rules' => []])
        ->assertForbidden();

    expect($schedule->fresh()->name)->toBe('Leasing hours')
        ->and(AvailabilitySchedule::query()->where('name', 'Sneaky hours')->exists())->toBeFalse();
});

test('shared hours govern every host, not just the event type owner', function () {
    $schedule = sharedHours($this->team);

    personalHours($this->admin, '13:00:00', '17:00:00');
    personalHours($this->member, '13:00:00', '17:00:00');

    $eventType = EventType::factory()->ownedBy($this->admin)->create([
        'team_id' => $this->team->id,
        'kind' => EventTypeKind::RoundRobin,
        'duration_minutes' => 60,
        'availability_schedule_id' => $schedule->id,
    ]);

    $eventType->hosts()->attach($this->admin, ['priority' => 0]);
    $eventType->hosts()->attach($this->member, ['priority' => 1]);

    $slots = app(AvailabilityEngine::class)->slots($eventType, wednesdayWindow());

    // 09:00-12:00 from the shared hours, not 13:00-17:00 from either host.
    expect($slots)->toHaveCount(3)
        ->and($slots->first()->startsAt->toTimeString())->toBe('09:00:00')
        ->and($slots->last()->endsAt->toTimeString())->toBe('12:00:00');
});

test('a team keeps its own hours for the event types it hosts', function () {
    $schedule = sharedHours($this->team, '10:00:00', '12:00:00');

    $group = Group::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Leasing',
        'availability_schedule_id' => $schedule->id,
    ]);

    $group->members()->attach($this->member, ['priority' => 0]);

    personalHours($this->member);

    $eventType = EventType::factory()->ownedBy($this->admin)->create([
        'team_id' => $this->team->id,
        'kind' => EventTypeKind::RoundRobin,
        'duration_minutes' => 60,
        'group_id' => $group->id,
    ]);

    $slots = app(AvailabilityEngine::class)->slots($eventType, wednesdayWindow());

    expect($slots)->toHaveCount(2)
        ->and($slots->first()->startsAt->toTimeString())->toBe('10:00:00');
});

test('a team can only be given hours the organization shares', function () {
    $stranger = User::factory()->create();
    $personal = personalHours($stranger);

    $group = Group::factory()->create(['team_id' => $this->team->id]);

    $this->actingAs($this->admin)
        // Group binds by slug, not id.
        ->patch(route('groups.update', [
            'current_team' => $this->team->slug,
            'group' => $group->slug,
        ]), [
            'name' => $group->name,
            'availability_schedule_id' => $personal->id,
            'member_ids' => [$this->member->id],
        ])
        ->assertSessionHasErrors('availability_schedule_id');

    expect($group->fresh()->availability_schedule_id)->toBeNull();
});

test('the event type picker offers the organizations hours alongside your own', function () {
    sharedHours($this->team);
    personalHours($this->admin);

    $this->actingAs($this->admin)
        ->get(route('scheduling.index', ['current_team' => $this->team->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('schedules', 2)
            ->where('schedules.0.name', 'Working hours')
            ->where('schedules.1.name', 'Leasing hours (shared)'));
});

test('personal hours still govern an event type with no shared schedule', function () {
    personalHours($this->member, '09:00:00', '11:00:00');

    $eventType = EventType::factory()->ownedBy($this->member)->create([
        'team_id' => $this->team->id,
        'duration_minutes' => 60,
    ]);

    $slots = app(AvailabilityEngine::class)->slots($eventType, wednesdayWindow());

    expect($slots)->toHaveCount(2)
        ->and($slots->first()->startsAt->toTimeString())->toBe('09:00:00');
});
