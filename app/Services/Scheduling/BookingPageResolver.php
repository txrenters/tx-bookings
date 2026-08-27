<?php

namespace App\Services\Scheduling;

use App\Models\EventType;
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
