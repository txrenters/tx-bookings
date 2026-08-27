<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Open registration for an address.
 *
 * Signup is invitation-only (see App\Actions\Fortify\CreateNewUser), so any
 * test that exercises the register endpoint has to create the invitation the
 * real flow would have created first.
 */
function invitationFor(string $email, ?string $teamName = null): TeamInvitation
{
    $owner = User::factory()->create();
    $team = Team::factory()->create(['name' => $teamName ?? 'Laravel Team']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    return TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => $email,
        'invited_by' => $owner->id,
    ]);
}
