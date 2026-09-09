<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

/**
 * The directory lists every account in the installation and can send any of
 * them a password reset, so it is operator-only: these pin the manageUsers
 * gate rather than any organization level permission.
 */
function superAdminUser(): User
{
    return User::factory()->create(['is_super_admin' => true]);
}

test('a super admin sees every account and the organizations it belongs to', function () {
    $team = Team::factory()->create(['name' => 'TexasRenters.com']);
    $member = User::factory()->create(['name' => 'Aaron Diaz', 'email' => 'aaron@example.com']);

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs(superAdminUser())
        ->get(route('users.index', ['search' => 'aaron']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users/Index')
            ->has('users', 1)
            ->where('users.0.email', 'aaron@example.com')
            ->where('users.0.isSuperAdmin', false)
            // The personal organization the factory creates, plus the one above.
            ->has('users.0.organizations', 2)
            ->where('users.0.organizations.1.name', 'TexasRenters.com')
            ->where('users.0.organizations.1.roleLabel', 'Member')
            ->where('users.0.calendar.state', 'none')
            ->has('users.0.groups', 0));
});

test('somebody who administers no organization cannot open the directory', function () {
    $user = User::factory()->create();
    $user->teams()->detach();

    $this->actingAs($user)->get(route('users.index'))->assertForbidden();
});

test('a super admin sends a password reset link', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs(superAdminUser())
        ->post(route('users.password-reset', ['user' => $user->id]))
        ->assertRedirect();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('an ordinary user cannot send a password reset for someone else', function () {
    Notification::fake();

    $target = User::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('users.password-reset', ['user' => $target->id]))
        ->assertForbidden();

    Notification::assertNothingSent();
});

test('a super admin changes the role a user holds in an organization', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs(superAdminUser())
        ->patch(route('users.role', ['user' => $member->id]), [
            'team_id' => $team->id,
            'role' => TeamRole::Admin->value,
        ])
        ->assertRedirect();

    expect($member->fresh()->teamRole($team))->toBe(TeamRole::Admin);
});

test('the directory refuses to demote the last administrator', function () {
    $team = Team::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs(superAdminUser())
        ->patch(route('users.role', ['user' => $admin->id]), [
            'team_id' => $team->id,
            'role' => TeamRole::Member->value,
        ])
        ->assertSessionHasErrors('role');

    expect($admin->fresh()->teamRole($team))->toBe(TeamRole::Admin);
});

test('a super admin deletes an account', function () {
    $user = User::factory()->create();

    $this->actingAs(superAdminUser())
        ->delete(route('users.destroy', ['user' => $user->id]))
        ->assertRedirect();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('a super admin cannot delete their own account here', function () {
    $admin = superAdminUser();

    $this->actingAs($admin)
        ->delete(route('users.destroy', ['user' => $admin->id]))
        ->assertSessionHasErrors('user');

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

test('an ordinary user cannot change roles or delete accounts', function () {
    $team = Team::factory()->create();
    $target = User::factory()->create();

    $team->members()->attach($target, ['role' => TeamRole::Member->value]);

    $actor = User::factory()->create();

    $this->actingAs($actor)
        ->patch(route('users.role', ['user' => $target->id]), [
            'team_id' => $team->id,
            'role' => TeamRole::Admin->value,
        ])
        ->assertForbidden();

    $this->actingAs($actor)
        ->delete(route('users.destroy', ['user' => $target->id]))
        ->assertForbidden();

    expect($target->fresh()->teamRole($team))->toBe(TeamRole::Member);
});

test('an organization admin sees only their own people, without the actions', function () {
    $team = Team::factory()->create();
    $admin = User::factory()->create();
    $colleague = User::factory()->create(['name' => 'Colleague']);

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($colleague, ['role' => TeamRole::Member->value]);

    // Somebody in another organization entirely.
    User::factory()->create(['name' => 'Stranger']);

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canManage', false)
            ->has('users', 2));
});

test('an organization admin cannot act on the people they can see', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $admin = User::factory()->create();
    $colleague = User::factory()->create();

    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($colleague, ['role' => TeamRole::Member->value]);

    $this->actingAs($admin)
        ->post(route('users.password-reset', ['user' => $colleague->id]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->patch(route('users.role', ['user' => $colleague->id]), [
            'team_id' => $team->id,
            'role' => TeamRole::Admin->value,
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('users.destroy', ['user' => $colleague->id]))
        ->assertForbidden();

    expect($colleague->fresh()->teamRole($team))->toBe(TeamRole::Member);
});

test('a plain member cannot open the directory at all', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();

    $member->teams()->detach();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member)->get(route('users.index'))->assertForbidden();
});

test('a super admin creates another super admin', function () {
    Notification::fake();

    $this->actingAs(superAdminUser())
        ->post(route('users.store'), [
            'name' => 'Second Operator',
            'email' => 'operator@texasrenters.com',
            'is_super_admin' => true,
        ])
        ->assertRedirect();

    $created = User::query()->where('email', 'operator@texasrenters.com')->sole();

    expect($created->isSuperAdmin())->toBeTrue()
        ->and($created->teams()->count())->toBe(0)
        // Bootstrapped like every other account-creating path.
        ->and($created->availabilitySchedules()->count())->toBe(1)
        ->and($created->email_verified_at)->not->toBeNull();

    Notification::assertSentTo($created, ResetPassword::class);
});

test('a super admin creates an admin inside an organization', function () {
    Notification::fake();

    $team = Team::factory()->create();

    $this->actingAs(superAdminUser())
        ->post(route('users.store'), [
            'name' => 'Org Admin',
            'email' => 'orgadmin@texasrenters.com',
            'team_id' => $team->id,
            'role' => TeamRole::Admin->value,
        ])
        ->assertRedirect();

    $created = User::query()->where('email', 'orgadmin@texasrenters.com')->sole();

    expect($created->isSuperAdmin())->toBeFalse()
        ->and($created->teamRole($team))->toBe(TeamRole::Admin);

    Notification::assertSentTo($created, ResetPassword::class);
});

test('an account that is not a super admin needs an organization and a role', function () {
    $this->actingAs(superAdminUser())
        ->post(route('users.store'), [
            'name' => 'Nowhere',
            'email' => 'nowhere@texasrenters.com',
        ])
        ->assertSessionHasErrors(['team_id', 'role']);

    $this->assertDatabaseMissing('users', ['email' => 'nowhere@texasrenters.com']);
});

test('a member cannot create accounts', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();

    $member->teams()->detach();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member)
        ->post(route('users.store'), [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'team_id' => $team->id,
            'role' => TeamRole::Member->value,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
});

test('an organization admin creates an account in their own organization', function () {
    Notification::fake();

    $team = Team::factory()->create();
    $admin = User::factory()->create();

    $admin->teams()->detach();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'New Colleague',
            'email' => 'colleague@texasrenters.com',
            'team_id' => $team->id,
            'role' => TeamRole::Member->value,
        ])
        ->assertRedirect();

    $created = User::query()->where('email', 'colleague@texasrenters.com')->sole();

    expect($created->teamRole($team))->toBe(TeamRole::Member);

    Notification::assertSentTo($created, ResetPassword::class);
});

test('an admin cannot create a super admin', function () {
    $team = Team::factory()->create();
    $admin = User::factory()->create();

    $admin->teams()->detach();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Operator',
            'email' => 'operator@texasrenters.com',
            'is_super_admin' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'operator@texasrenters.com']);
});

test('an admin cannot create an account in an organization they do not administer', function () {
    $ownTeam = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $admin = User::factory()->create();

    $admin->teams()->detach();
    $ownTeam->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $otherTeam->members()->attach($admin, ['role' => TeamRole::Member->value]);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Elsewhere',
            'email' => 'elsewhere@texasrenters.com',
            'team_id' => $otherTeam->id,
            'role' => TeamRole::Member->value,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'elsewhere@texasrenters.com']);
});
