<?php

namespace App\Services\Scheduling;

use App\Enums\EventTypeKind;
use App\Models\EventType;
use App\Models\Group;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves a public booking page slug to the user or team behind it, and
 * scopes event type lookups to that page.
 */
class BookingPageResolver
{
    /**
     * Find the owner of a public booking page.
     */
    public function resolve(string $slug): User|Team|null
    {
        $user = User::query()->where('booking_slug', $slug)->first();

        if ($user !== null) {
            return $user;
        }

        return Team::query()->where('slug', $slug)->first();
    }

    /**
     * Get the event types listed on a page.
     *
     * @return Collection<int, EventType>
     */
    public function eventTypes(User|Team $page): Collection
    {
        return $this->query($page)
            ->where('is_hidden', false)
            /*
             * An organization page advertises only the shared kinds. A person's
             * own one-on-ones belong on their personal link, not on the front
             * door of the organization -- but they still RESOLVE here, so any
             * link already handed out keeps working.
             */
            ->when(
                $page instanceof Team,
                fn (Builder $query) => $query->whereIn('kind', EventTypeKind::shared()),
            )
            ->orderBy('name')
            ->get();
    }

    /**
     * Find a team (the Group model) on a page by its slug.
     *
     * Only an organization page has teams beneath it; a personal booking page
     * has none, so it never resolves one.
     */
    public function group(User|Team $page, string $slug): ?Group
    {
        if (! $page instanceof Team) {
            return null;
        }

        return Group::query()
            ->where('team_id', $page->id)
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Get the event types listed on a team's own landing page.
     *
     * @return Collection<int, EventType>
     */
    public function eventTypesForGroup(Group $group): Collection
    {
        return $this->query($group->team)
            ->where('is_hidden', false)
            ->where('group_id', $group->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * Find a single event type on a page by its slug.
     *
     * Hidden event types resolve directly so their links keep working.
     */
    public function eventType(User|Team $page, string $slug): ?EventType
    {
        return $this->query($page)->where('slug', $slug)->first();
    }

    /**
     * Get the display name for a page.
     */
    public function name(User|Team $page): string
    {
        return $page->name;
    }

    /**
     * Get the logo to brand a page with.
     *
     * A personal page has no logo of its own, so it inherits the one from the
     * organization the person is currently in — a booking page should still
     * carry the company's brand even when it belongs to one member.
     */
    public function logoUrl(User|Team $page): ?string
    {
        $team = $page instanceof Team ? $page : $page->currentTeam;

        return $team?->logoUrl();
    }

    /**
     * Get the website to link a page back to, using the same fallback.
     */
    public function websiteUrl(User|Team $page): ?string
    {
        $team = $page instanceof Team ? $page : $page->currentTeam;

        return $team?->website_url;
    }

    /**
     * Build the base query for the event types belonging to a page.
     *
     * @return Builder<EventType>
     */
    protected function query(User|Team $page): Builder
    {
        return EventType::query()
            ->bookable()
            ->with(['owner', 'hosts', 'questions', 'team'])
            ->when(
                $page instanceof User,
                fn (Builder $query) => $query->where('user_id', $page->id),
                fn (Builder $query) => $query->where('team_id', $page->id),
            );
    }
}
