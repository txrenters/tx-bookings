<?php

use App\Models\Team;
use App\Models\User;

/**
 * People and organizations share one /book/{slug} namespace and the resolver
 * checks people first, so a collision would silently hide an organization's
 * booking page. Neither generator may hand out a slug the other already holds.
 */
test('a personal booking slug does not collide with an existing organization', function () {
    Team::factory()->create(['name' => 'Lone Star Rentals', 'slug' => 'lone-star-rentals']);

    $user = User::factory()->create(['name' => 'Lone Star Rentals']);

    expect($user->booking_slug)->not->toBe('lone-star-rentals')
        ->and($user->booking_slug)->toStartWith('lone-star-rentals-');
});

test('an organization slug does not collide with an existing personal page', function () {
    $user = User::factory()->create(['name' => 'Bluebonnet Homes']);

    expect($user->booking_slug)->toBe('bluebonnet-homes');

    $team = Team::factory()->create(['name' => 'Bluebonnet Homes', 'slug' => null]);

    expect($team->slug)->not->toBe('bluebonnet-homes');
});

test('both pages stay reachable once the slugs are distinct', function () {
    $team = Team::factory()->create(['name' => 'Hill Country Living', 'slug' => 'hill-country-living']);
    $user = User::factory()->create(['name' => 'Hill Country Living']);

    $this->get(route('book.page', ['page' => $team->slug]))->assertOk();
    $this->get(route('book.page', ['page' => $user->booking_slug]))->assertOk();

    expect($team->slug)->not->toBe($user->booking_slug);
});
