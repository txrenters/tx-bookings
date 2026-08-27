<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Build a super admin who belongs to no organization at all.
 */
function superAdmin(): User
{
    return User::factory()->create([
        'is_super_admin' => true,
        'email_verified_at' => now(),
    ]);
}

/**
 * Build an organization the super admin is not a member of.
 */
function foreignOrganization(): Team
{
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    return $team;
}

test('a super admin reaches an organization they do not belong to', function () {
    $admin = superAdmin();
    $team = foreignOrganization();

    expect($admin->belongsToTeam($team))->toBeFalse();

    $this->actingAs($admin)
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertOk();
});

test('an ordinary user still cannot reach someone elses organization', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $team = foreignOrganization();

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertForbidden();
});

test('a super admin can switch into any organization', function () {
    $admin = superAdmin();
    $team = foreignOrganization();

    $this->actingAs($admin)
        ->post(route('teams.switch', $team->slug))
        ->assertRedirect();

    expect($admin->fresh()->current_team_id)->toBe($team->id);
});

test('the switcher lists every organization for a super admin', function () {
    $admin = superAdmin();
    foreignOrganization();
    foreignOrganization();

    $slugs = $admin->toUserTeams(includeCurrent: true)->pluck('slug');

    expect($slugs)->toHaveCount(Team::query()->count());
});

test('a super admin has full permissions in an organization they do not belong to', function () {
    $admin = superAdmin();
    $team = foreignOrganization();

    $permissions = $admin->toTeamPermissions($team);

    expect($permissions->canUpdateTeam)->toBeTrue()
        ->and($permissions->canRemoveMember)->toBeTrue();
});

test('the log viewer is reachable only by a super admin', function () {
    $this->actingAs(superAdmin())->get(route('logs.index'))->assertOk();

    $ordinary = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($ordinary)->get(route('logs.index'))->assertNotFound();
});

test('the super admin flag is shared with the front end', function () {
    $admin = superAdmin();
    $team = foreignOrganization();

    $this->actingAs($admin)
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertInertia(fn ($page) => $page->where('isSuperAdmin', true));
});

test('the command promotes an existing account without touching its password', function () {
    $user = User::factory()->create(['email' => 'ops@texasrenters.com']);
    $before = $user->password;

    $this->artisan('user:super-admin', ['email' => 'OPS@texasrenters.com'])
        ->assertExitCode(0);

    $user->refresh();

    expect($user->is_super_admin)->toBeTrue()
        ->and($user->password)->toBe($before);
});

test('the command creates a verified account when none exists', function () {
    $this->artisan('user:super-admin', [
        'email' => 'root@texasrenters.com',
        '--password' => 'a-known-password',
    ])->assertExitCode(0);

    $user = User::query()->where('email', 'root@texasrenters.com')->sole();

    expect($user->is_super_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-known-password', $user->password))->toBeTrue();
});

test('the command can revoke the role again', function () {
    $admin = superAdmin();

    $this->artisan('user:super-admin', ['email' => $admin->email, '--revoke' => true])
        ->assertExitCode(0);

    expect($admin->fresh()->is_super_admin)->toBeFalse();
});

test('the command refuses a malformed address', function () {
    $this->artisan('user:super-admin', ['email' => 'not-an-email'])
        ->assertExitCode(1);
});
