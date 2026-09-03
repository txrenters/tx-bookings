<?php

use App\Enums\EventTypeKind;
use App\Models\EventType;
use App\Models\Group;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * A team (the Group model) gets its own public landing page at
 * /book/{organization}/{team}, listing only the event types pointed at it.
 * The organization page keeps listing everything.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;

    $this->group = Group::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Leasing',
        'slug' => 'leasing',
    ]);

    $this->grouped = EventType::factory()->ownedBy($this->user)->create([
        'name' => 'Tour a property',
        'slug' => 'tour-a-property',
        'group_id' => $this->group->id,
    ]);

    $this->ungrouped = EventType::factory()->ownedBy($this->user)->create([
        'name' => 'Discovery call',
        'slug' => 'discovery-call',
    ]);
});

test('a team page lists only its own event types', function () {
    $this->get("/book/{$this->team->slug}/leasing")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('book/Page')
            ->where('page.name', 'Leasing')
            ->where('parent.name', $this->team->name)
            ->has('eventTypes', 1)
            ->where('eventTypes.0.slug', 'tour-a-property'),
        );
});

/*
 * An organization page advertises what the organization offers as a whole, so
 * it lists only the pooled kinds. Both fixtures above are one-on-ones, which
 * belong on their owner's personal link.
 */
test('the organization page lists only shared event types', function () {
    $shared = EventType::factory()->ownedBy($this->user)->create([
        'name' => 'Property tour',
        'slug' => 'property-tour',
        'kind' => EventTypeKind::RoundRobin,
        'group_id' => $this->group->id,
    ]);

    $this->get("/book/{$this->team->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('book/Page')
            ->has('eventTypes', 1)
            ->where('eventTypes.0.slug', $shared->slug),
        );
});

/*
 * Not listed is not the same as not reachable: a link already handed out for a
 * personal event type must keep working from the organization page.
 */
test('an unlisted personal event type still resolves by direct link', function () {
    $this->get("/book/{$this->team->slug}/discovery-call")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('book/EventType'));
});

test('a personal page still lists the owner own event types', function () {
    $this->user->forceFill(['booking_slug' => 'sam'])->save();

    $this->get('/book/sam')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('book/Page')
            ->has('eventTypes', 2),
        );
});

/*
 * A team page and an event type share the /book/{page}/{slug} shape, so the
 * order they resolve in decides which wins. Event types go first, or naming a
 * team after one would silently break its booking links.
 */
test('an event type wins over a team with the same slug', function () {
    Group::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'Discovery call',
        'slug' => 'discovery-call',
    ]);

    $this->get("/book/{$this->team->slug}/discovery-call")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('book/EventType'));
});

test('a team from another organization is not reachable', function () {
    $other = User::factory()->create();

    Group::factory()->create([
        'team_id' => $other->currentTeam->id,
        'slug' => 'maintenance',
    ]);

    $this->get("/book/{$this->team->slug}/maintenance")->assertNotFound();
});

test('a personal booking page has no team pages', function () {
    $this->user->forceFill(['booking_slug' => 'sam'])->save();

    $this->get('/book/sam/leasing')->assertNotFound();
});
