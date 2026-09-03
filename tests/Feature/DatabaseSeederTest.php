<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('seeding creates default accounts that are ready to log in', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('email', 'automation@texasrenters.com')->first();

    expect($user)->not->toBeNull()
        ->and(Hash::check('password', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->currentTeam)->not->toBeNull()
        ->and($user->availabilitySchedules()->count())->toBe(1);
});

test('reseeding leaves existing accounts untouched', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'test@example.com')->count())->toBe(1)
        ->and(User::query()->count())->toBe(2);
});
