<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Build an organization owned by a fresh user.
 *
 * @return array{0: User, 1: Team}
 */
function ownedTeam(array $attributes = []): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create($attributes);

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    return [$user, $team];
}

test('an owner can upload an organization logo', function () {
    Storage::fake('public');

    [$user, $team] = ownedTeam();

    $this
        ->actingAs($user)
        ->patch(route('teams.update', $team), [
            'name' => $team->name,
            'logo' => UploadedFile::fake()->image('logo.png', 400, 400),
        ])
        ->assertRedirect();

    $path = $team->fresh()->logo_path;

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('uploading a replacement removes the previous logo file', function () {
    Storage::fake('public');

    [$user, $team] = ownedTeam();

    $this->actingAs($user)->patch(route('teams.update', $team), [
        'name' => $team->name,
        'logo' => UploadedFile::fake()->image('first.png', 400, 400),
    ]);

    $first = $team->fresh()->logo_path;

    $this->actingAs($user)->patch(route('teams.update', $team), [
        'name' => $team->name,
        'logo' => UploadedFile::fake()->image('second.png', 400, 400),
    ]);

    $second = $team->fresh()->logo_path;

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

test('an owner can remove the organization logo', function () {
    Storage::fake('public');

    [$user, $team] = ownedTeam();

    $this->actingAs($user)->patch(route('teams.update', $team), [
        'name' => $team->name,
        'logo' => UploadedFile::fake()->image('logo.png', 400, 400),
    ]);

    $path = $team->fresh()->logo_path;

    $this->actingAs($user)->patch(route('teams.update', $team), [
        'name' => $team->name,
        'remove_logo' => true,
    ]);

    expect($team->fresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('an update that says nothing about the logo leaves it alone', function () {
    Storage::fake('public');

    [$user, $team] = ownedTeam();

    $this->actingAs($user)->patch(route('teams.update', $team), [
        'name' => $team->name,
        'logo' => UploadedFile::fake()->image('logo.png', 400, 400),
    ]);

    $path = $team->fresh()->logo_path;

    $this->actingAs($user)->patch(route('teams.update', $team), [
        'name' => 'Renamed Organization',
    ]);

    expect($team->fresh()->logo_path)->toBe($path);
    Storage::disk('public')->assertExists($path);
});

test('svg logos are rejected because they are served from our own origin', function () {
    Storage::fake('public');

    [$user, $team] = ownedTeam();

    $this
        ->actingAs($user)
        ->patch(route('teams.update', $team), [
            'name' => $team->name,
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('logo');

    expect($team->fresh()->logo_path)->toBeNull();
});

test('an oversized logo is rejected', function () {
    Storage::fake('public');

    [$user, $team] = ownedTeam();

    $this
        ->actingAs($user)
        ->patch(route('teams.update', $team), [
            'name' => $team->name,
            'logo' => UploadedFile::fake()->image('huge.png', 2400, 2400),
        ])
        ->assertSessionHasErrors('logo');
});

test('an owner can save the rest of the organization details', function () {
    [$user, $team] = ownedTeam();

    $this
        ->actingAs($user)
        ->patch(route('teams.update', $team), [
            'name' => $team->name,
            'welcome_message' => 'Book a tour with our leasing team.',
            'website_url' => 'https://texasrenters.com',
            'timezone' => 'America/Chicago',
        ])
        ->assertRedirect();

    $team->refresh();

    expect($team->welcome_message)->toBe('Book a tour with our leasing team.')
        ->and($team->website_url)->toBe('https://texasrenters.com')
        ->and($team->timezone)->toBe('America/Chicago');
});

test('an invalid website url is rejected', function () {
    [$user, $team] = ownedTeam();

    $this
        ->actingAs($user)
        ->patch(route('teams.update', $team), [
            'name' => $team->name,
            'website_url' => 'not-a-url',
        ])
        ->assertSessionHasErrors('website_url');
});

test('the public booking page shows the organization logo', function () {
    Storage::fake('public');

    [$user, $team] = ownedTeam();

    $this->actingAs($user)->patch(route('teams.update', $team), [
        'name' => $team->name,
        'logo' => UploadedFile::fake()->image('logo.png', 400, 400),
    ]);

    $this
        ->get(route('book.page', ['page' => $team->fresh()->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('page.logoUrl', $team->fresh()->logoUrl())
        );
});

test('a personal booking page inherits the organization logo', function () {
    Storage::fake('public');

    [$user, $team] = ownedTeam();

    $user->forceFill(['current_team_id' => $team->id])->save();

    $this->actingAs($user)->patch(route('teams.update', $team), [
        'name' => $team->name,
        'logo' => UploadedFile::fake()->image('logo.png', 400, 400),
    ]);

    // Booked through the person's own slug, not the organization's.
    $this
        ->get(route('book.page', ['page' => $user->fresh()->booking_slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('page.logoUrl', $team->fresh()->logoUrl())
        );
});
