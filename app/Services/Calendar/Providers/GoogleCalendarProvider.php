<?php

namespace App\Services\Calendar\Providers;

use App\Data\Calendar\ExternalCalendar;
use App\Data\Calendar\ExternalEvent;
use App\Data\Calendar\ProviderIdentity;
use App\Enums\CalendarProvider;
use App\Models\Booking;
use App\Models\CalendarAccount;
use App\Services\Calendar\CalendarProviderContract;
use App\Services\Ics\IcsGenerator;
use App\Support\TimeRange;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleCalendarProvider implements CalendarProviderContract
{
    protected const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    protected const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    protected const API_URL = 'https://www.googleapis.com/calendar/v3';

    /**
     * The scopes required to read busy times and manage our own events.
     *
     * @var array<int, string>
     */
    protected array $scopes = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/calendar.readonly',
        'https://www.googleapis.com/auth/calendar.events',
    ];

    public function __construct(protected IcsGenerator $ics)
    {
        //
    }

    /**
     * Build the URL that starts the consent flow.
     */
    public function authorizationUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => $this->config('client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * Trade an authorization code for tokens and the account identity.
     */
    public function exchangeCode(string $code): ProviderIdentity
    {
        $tokens = Http::asForm()
            ->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => $this->config('client_id'),
                'client_secret' => $this->config('client_secret'),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ])
            ->throw()
            ->json();

        $profile = Http::withToken($tokens['access_token'])
            ->get(self::USERINFO_URL)
            ->throw()
            ->json();

        return new ProviderIdentity(
            externalId: (string) ($profile['sub'] ?? $profile['email']),
            email: (string) $profile['email'],
            accessToken: $tokens['access_token'],
            refreshToken: $tokens['refresh_token'] ?? null,
            expiresAt: isset($tokens['expires_in'])
                ? CarbonImmutable::now()->addSeconds((int) $tokens['expires_in'])
                : null,
        );
    }

    /**
     * Refresh an expired access token in place.
     */
    public function refreshToken(CalendarAccount $account): void
    {
        if (blank($account->refresh_token)) {
            throw new RuntimeException('The Google account has no refresh token; it must be reconnected.');
        }

        $tokens = Http::asForm()
            ->post(self::TOKEN_URL, [
                'refresh_token' => $account->refresh_token,
                'client_id' => $this->config('client_id'),
                'client_secret' => $this->config('client_secret'),
                'grant_type' => 'refresh_token',
            ])
            ->throw()
            ->json();

        $account->update([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($tokens['expires_in'])
                ? CarbonImmutable::now()->addSeconds((int) $tokens['expires_in'])
                : null,
        ]);
    }

    /**
     * List the calendars available on the account.
     *
     * @return Collection<int, ExternalCalendar>
     */
    public function listCalendars(CalendarAccount $account): Collection
    {
        $items = $this->request($account)
            ->get(self::API_URL.'/users/me/calendarList')
            ->throw()
            ->json('items', []);

        return (new Collection($items))->map(fn (array $item) => new ExternalCalendar(
            id: $item['id'],
            name: $item['summary'] ?? $item['id'],
            isPrimary: (bool) ($item['primary'] ?? false),
            timezone: $item['timeZone'] ?? null,
        ))->values();
    }

    /**
     * Get the busy periods across the given calendars.
     *
     * @param  array<int, string>  $calendarIds
     * @return Collection<int, TimeRange>
     */
    public function busyPeriods(CalendarAccount $account, array $calendarIds, TimeRange $window): Collection
    {
        if ($calendarIds === []) {
            return new Collection;
        }

        $response = $this->request($account)
            ->post(self::API_URL.'/freeBusy', [
                'timeMin' => $window->start->toRfc3339String(),
                'timeMax' => $window->end->toRfc3339String(),
                'items' => array_map(fn (string $id) => ['id' => $id], $calendarIds),
            ])
            ->throw()
            ->json('calendars', []);

        /** @var Collection<int, TimeRange> $ranges */
        $ranges = new Collection;

        foreach ($response as $calendar) {
            foreach ($calendar['busy'] ?? [] as $busy) {
                $ranges->push(TimeRange::make($busy['start'], $busy['end']));
            }
        }

        return $ranges;
    }

    /**
     * Write a booking to the account's calendar.
     */
    public function createEvent(CalendarAccount $account, string $calendarId, Booking $booking, bool $withOnlineMeeting): ExternalEvent
    {
        $payload = [
            'summary' => $this->ics->title($booking),
            'description' => $this->ics->description($booking),
            'start' => ['dateTime' => $booking->starts_at->toRfc3339String(), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $booking->ends_at->toRfc3339String(), 'timeZone' => 'UTC'],
            'attendees' => $this->attendees($booking),
            'reminders' => ['useDefault' => true],
        ];

        if (filled($booking->location_detail) && ! $withOnlineMeeting) {
            $payload['location'] = $booking->location_detail;
        }

        if ($withOnlineMeeting) {
            $payload['conferenceData'] = [
                'createRequest' => [
                    'requestId' => (string) Str::uuid(),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ];
        }

        $event = $this->request($account)
            ->post(self::API_URL."/calendars/{$calendarId}/events?conferenceDataVersion=1&sendUpdates=none", $payload)
            ->throw()
            ->json();

        return new ExternalEvent(
            id: $event['id'],
            calendarId: $calendarId,
            meetingUrl: $event['hangoutLink'] ?? data_get($event, 'conferenceData.entryPoints.0.uri'),
        );
    }

    /**
     * Remove a previously written event.
     */
    public function deleteEvent(CalendarAccount $account, string $calendarId, string $eventId): void
    {
        $response = $this->request($account)
            ->delete(self::API_URL."/calendars/{$calendarId}/events/{$eventId}");

        // A already deleted event is not an error worth surfacing.
        if ($response->status() !== 404 && $response->status() !== 410) {
            $response->throw();
        }
    }

    /**
     * Get an HTTP client authenticated as the account.
     */
    protected function request(CalendarAccount $account): PendingRequest
    {
        return Http::withToken($account->access_token)->acceptJson();
    }

    /**
     * Build the attendee list for a calendar event.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function attendees(Booking $booking): array
    {
        $attendees = [['email' => $booking->email, 'displayName' => $booking->name]];

        foreach ($booking->hosts as $host) {
            $attendees[] = ['email' => $host->email, 'displayName' => $host->name, 'organizer' => $host->id === $booking->user_id];
        }

        foreach ($booking->guests as $guest) {
            $attendees[] = ['email' => $guest->email];
        }

        return $attendees;
    }

    /**
     * Read a value from the provider's service configuration.
     */
    protected function config(string $key): ?string
    {
        return config(CalendarProvider::Google->configKey().'.'.$key);
    }

    /**
     * Get the OAuth redirect URI for this provider.
     */
    protected function redirectUri(): string
    {
        return route('integrations.callback', ['provider' => CalendarProvider::Google->value]);
    }
}
