<?php

use App\Enums\TeamRole;
use App\Models\AvailabilityOverride;
use App\Models\AvailabilityRule;
use App\Models\AvailabilitySchedule;
use App\Models\EventType;
use App\Models\Team;
use App\Models\User;

/**
 * Wall clock columns must always land as H:i:s.
 *
 * The UI posts "09:00" and Calendly returns "09:00", so an un-normalised writer
 * produced values that crashed the scheduling page when
 * AvailabilitySchedule::summary() parsed them. These assert the model
 * normalises on write, rather than relying on the database column to do it.
 */
test('a rule written with H:i is stored as H:i:s', function () {
    $schedule = AvailabilitySchedule::factory()->for(User::factory())->create();

    $rule = AvailabilityRule::create([
        'availability_schedule_id' => $schedule->id,
        'day_of_week' => 1,
        'starts_at' => '09:00',
        'ends_at' => '17:30',
    ]);

    expect($rule->fresh()->starts_at)->toBe('09:00:00')
        ->and($rule->fresh()->ends_at)->toBe('17:30:00');
});

test('a value already in H:i:s is left alone', function () {
    $schedule = AvailabilitySchedule::factory()->for(User::factory())->create();

    $rule = AvailabilityRule::create([
        'availability_schedule_id' => $schedule->id,
        'day_of_week' => 2,
        'starts_at' => '08:15:00',
        'ends_at' => '12:00:00',
    ]);

    expect($rule->fresh()->starts_at)->toBe('08:15:00');
});

test('overrides are normalised the same way', function () {
    $schedule = AvailabilitySchedule::factory()->for(User::factory())->create();

    $override = AvailabilityOverride::create([
        'availability_schedule_id' => $schedule->id,
        'date' => '2026-12-24',
        'starts_at' => '10:00',
        'ends_at' => '14:00',
        'is_unavailable' => false,
    ]);

    expect($override->fresh()->starts_at)->toBe('10:00:00')
        ->and($override->fresh()->ends_at)->toBe('14:00:00');
});

test('a schedule summary renders hours written in either shape', function () {
    $user = User::factory()->create();
    $schedule = AvailabilitySchedule::factory()->for($user)->create();

    AvailabilityRule::create([
        'availability_schedule_id' => $schedule->id,
        'day_of_week' => 1,
        'starts_at' => '09:00',
        'ends_at' => '17:00',
    ]);

    // Both ends keep their meridiem here because they differ.
    expect($schedule->fresh('rules')->summary())->toContain('9 am - 5 pm');
});

test('the scheduling page survives hours that arrived from an import', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);
    $user->forceFill(['current_team_id' => $team->id])->save();

    $schedule = AvailabilitySchedule::factory()->for($user)->create();

    AvailabilityRule::create([
        'availability_schedule_id' => $schedule->id,
        'day_of_week' => 3,
        'starts_at' => '09:00',
        'ends_at' => '17:00',
    ]);

    EventType::factory()->ownedBy($user)->create([
        'team_id' => $team->id,
        'availability_schedule_id' => $schedule->id,
    ]);

    $this->actingAs($user)
        ->get(route('scheduling.index', ['current_team' => $team->slug]))
        ->assertOk();
});
