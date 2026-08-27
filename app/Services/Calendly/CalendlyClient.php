<?php

namespace App\Services\Calendly;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A thin read-only wrapper over the Calendly v2 API.
 *
 * Deliberately plain HTTP rather than an SDK, matching how the Google and
 * Microsoft calendar providers are written — nothing to keep in sync and
 * Http::fake() covers it in tests.
 */
class CalendlyClient
{
    /**
     * The largest page Calendly will return.
     */
    protected int $pageSize = 100;

    public function __construct(
        protected ?string $token = null,
        protected ?string $baseUrl = null,
    ) {
        $this->token ??= config('services.calendly.token');
        $this->baseUrl ??= rtrim((string) config('services.calendly.base_url'), '/');
    }

    /**
     * Determine whether a token has been configured.
     */
    public function isConfigured(): bool
    {
        return filled($this->token);
    }

    /**
     * Get the account the token belongs to.
     *
     * @return array<string, mixed>
     */
    public function currentUser(): array
    {
        return $this->get('/users/me')['resource'] ?? [];
    }

    /**
     * List the members of an organization.
     *
     * @return array<int, array<string, mixed>>
     */
    public function organizationMemberships(string $organizationUri): array
    {
        return $this->collection('/organization_memberships', ['organization' => $organizationUri]);
    }

    /**
     * List the groups (teams) defined on an organization.
     *
     * Calendly exposes the group itself but not its membership, so only the
     * names come across.
     *
     * @return array<int, array<string, mixed>>
     */
    public function groups(string $organizationUri): array
    {
        return $this->collection('/groups', ['organization' => $organizationUri]);
    }

    /**
     * List every event type in an organization, not just one person's.
     *
     * @return array<int, array<string, mixed>>
     */
    public function organizationEventTypes(string $organizationUri): array
    {
        return $this->collection('/event_types', ['organization' => $organizationUri]);
    }

    /**
     * List every scheduled event in an organization.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function organizationScheduledEvents(string $organizationUri, array $filters = []): array
    {
        return $this->collection('/scheduled_events', ['organization' => $organizationUri, ...$filters]);
    }

    /**
     * List a user's event types.
     *
     * @return array<int, array<string, mixed>>
     */
    public function eventTypes(string $userUri): array
    {
        return $this->collection('/event_types', ['user' => $userUri]);
    }

    /**
     * List the hosts assigned to a pooled (round robin or collective) event type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function eventTypeHosts(string $eventTypeUri): array
    {
        return $this->collection('/event_type_memberships', ['event_type' => $eventTypeUri]);
    }

    /**
     * List a user's availability schedules.
     *
     * @return array<int, array<string, mixed>>
     */
    public function availabilitySchedules(string $userUri): array
    {
        return $this->collection('/user_availability_schedules', ['user' => $userUri]);
    }

    /**
     * List a user's scheduled events.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function scheduledEvents(string $userUri, array $filters = []): array
    {
        return $this->collection('/scheduled_events', ['user' => $userUri, ...$filters]);
    }

    /**
     * List the invitees of one scheduled event.
     *
     * @return array<int, array<string, mixed>>
     */
    public function eventInvitees(string $eventUri): array
    {
        return $this->collection($this->pathFor($eventUri).'/invitees');
    }

    /**
     * Walk every page of a collection endpoint and return the flattened rows.
     *
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    protected function collection(string $path, array $query = []): array
    {
        $rows = [];
        $token = null;

        do {
            $payload = $this->get($path, [
                ...$query,
                'count' => $this->pageSize,
                ...($token === null ? [] : ['page_token' => $token]),
            ]);

            foreach ($payload['collection'] ?? [] as $row) {
                $rows[] = $row;
            }

            $next = $payload['pagination']['next_page_token'] ?? null;
            // Calendly returns the same token forever if a page is malformed;
            // stopping on a repeat avoids an endless loop.
            $token = $next === $token ? null : $next;
        } while (filled($token));

        return $rows;
    }

    /**
     * Perform one GET request.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function get(string $path, array $query = [], int $attempt = 1): array
    {
        $response = $this->request()->get($this->baseUrl.$path, $query);

        if ($response->status() === 429) {
            /*
             * An organization-wide import makes hundreds of calls, so a rate
             * limit is expected rather than exceptional. Wait the window out
             * and try once more before giving up.
             */
            if ($attempt < 3) {
                sleep((int) ($response->header('Retry-After') ?: 20));

                return $this->get($path, $query, $attempt + 1);
            }

            throw new RuntimeException('Calendly rate limit reached repeatedly. Wait a minute and run the import again.');
        }

        if ($response->status() === 401) {
            throw new RuntimeException('Calendly rejected the API token. Check CALENDLY_API_KEY.');
        }

        if ($response->failed()) {
            throw new RuntimeException("Calendly request to {$path} failed with status {$response->status()}.");
        }

        return $response->json() ?? [];
    }

    /**
     * Build the base request.
     */
    protected function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('No Calendly API token configured. Set CALENDLY_API_KEY in your .env.');
        }

        return Http::withToken($this->token)
            ->acceptJson()
            ->timeout(30)
            ->retry(3, 500, throw: false);
    }

    /**
     * Reduce a full Calendly URI to the path this client can call.
     */
    protected function pathFor(string $uri): string
    {
        return str_starts_with($uri, 'http')
            ? (string) str($uri)->after($this->baseUrl)
            : $uri;
    }
}
