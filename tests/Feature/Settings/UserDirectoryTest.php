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

test('an ordinary user cannot open the directory', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('users.index'))
        ->assertForbidden();
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
