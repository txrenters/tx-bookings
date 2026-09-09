<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

/**
 * Looking across every organization is the operator's job, so these pin the
 * manageOrganizations gate as well as the behaviour.
 */
function operator(): User
{
    return User::factory()->create(['is_super_admin' => true]);
}

test('a super admin sees every organization and who runs it', function () {
    $team = Team::factory()->create(['name' => 'TexasRenters.com']);
    $admin = User::factory()->create(['name' => 'Ada Admin']);

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $this->actingAs(operator())
        ->get(route('organizations.index', ['search' => 'TexasRenters.com']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations/Index')
            ->has('organizations', 1)
            ->where('organizations.0.name', 'TexasRenters.com')
            ->has('organizations.0.members', 1)
            ->where('organizations.0.members.0.roleLabel', 'Admin'));
});

test('an ordinary user cannot open the organization directory', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('organizations.index'))
        ->assertForbidden();
});

test('an existing account is added to an organization as an admin', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $person = User::factory()->create(['email' => 'ada@example.com']);

    $this->actingAs(operator())
        ->post(route('organizations.members.store', ['team' => $team->slug]), [
            'email' => 'ada@example.com',
            'role' => TeamRole::Admin->value,
        ])
        ->assertRedirect();

    expect($person->fresh()->teamRole($team))->toBe(TeamRole::Admin);

    // Nothing was created, so nothing to set a password for.
    Notification::assertNothingSent();
});

test('an unknown address gets an account and a link to set a password', function () {
    Notification::fake();

    $team = Team::factory()->create();

    $this->actingAs(operator())
        ->post(route('organizations.members.store', ['team' => $team->slug]), [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'role' => TeamRole::Member->value,
        ])
        ->assertRedirect();

    $created = User::query()->where('email', 'new@example.com')->sole();

    expect($created->teamRole($team))->toBe(TeamRole::Member);

    Notification::assertSentTo($created, ResetPassword::class);
});

test('a new account needs a name', function () {
    $team = Team::factory()->create();

    $this->actingAs(operator())
        ->post(route('organizations.members.store', ['team' => $team->slug]), [
            'email' => 'nameless@example.com',
            'role' => TeamRole::Member->value,
        ])
        ->assertSessionHasErrors('name');
});

test('somebody already in the organization is not added twice', function () {
    $team = Team::factory()->create();
    $person = User::factory()->create(['email' => 'ada@example.com']);

    $team->members()->attach($person, ['role' => TeamRole::Member->value]);

    $this->actingAs(operator())
        ->post(route('organizations.members.store', ['team' => $team->slug]), [
            'email' => 'ada@example.com',
            'role' => TeamRole::Admin->value,
        ])
        ->assertSessionHasErrors('email');

    expect($team->memberships()->where('user_id', $person->id)->count())->toBe(1);
});

test('the last administrator is protected from demotion and removal', function () {
    $team = Team::factory()->create();
    $admin = User::factory()->create();

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $this->actingAs(operator())
        ->patch(route('organizations.members.update', ['team' => $team->slug, 'user' => $admin->id]), [
            'role' => TeamRole::Member->value,
        ])
        ->assertSessionHasErrors('role');

    $this->actingAs(operator())
        ->delete(route('organizations.members.destroy', ['team' => $team->slug, 'user' => $admin->id]))
        ->assertSessionHasErrors('member');

    expect($admin->fresh()->teamRole($team))->toBe(TeamRole::Admin);
});

test('a member is removed from the organization without deleting the account', function () {
    $team = Team::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs(operator())
        ->delete(route('organizations.members.destroy', ['team' => $team->slug, 'user' => $member->id]))
        ->assertRedirect();

    expect($member->fresh())->not->toBeNull()
        ->and($member->fresh()->belongsToTeam($team))->toBeFalse();
});

test('an organization admin cannot delete the organization', function () {
    $team = Team::factory()->create();
    $admin = User::factory()->create();

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $this->actingAs($admin)
        ->delete(route('teams.destroy', ['team' => $team->slug]), ['name' => $team->name])
        ->assertForbidden();

    expect(Team::query()->whereKey($team->id)->exists())->toBeTrue();
});
