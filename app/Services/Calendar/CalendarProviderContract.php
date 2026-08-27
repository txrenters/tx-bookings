<?php

namespace App\Services\Calendar;

use App\Data\Calendar\ExternalCalendar;
use App\Data\Calendar\ExternalEvent;
use App\Data\Calendar\ProviderIdentity;
use App\Models\Booking;
use App\Models\CalendarAccount;
use App\Support\TimeRange;
use Illuminate\Support\Collection;

interface CalendarProviderContract
{
    /**
     * Build the URL that starts the consent flow.
     */
    public function authorizationUrl(string $state): string;

    /**
     * Trade an authorization code for tokens and the account identity.
     */
    public function exchangeCode(string $code): ProviderIdentity;

    /**
     * Refresh an expired access token in place.
     */
    public function refreshToken(CalendarAccount $account): void;

    /**
     * List the calendars available on the account.
     *
     * @return Collection<int, ExternalCalendar>
     */
    public function listCalendars(CalendarAccount $account): Collection;

    /**
     * Get the busy periods across the given calendars.
     *
     * @param  array<int, string>  $calendarIds
     * @return Collection<int, TimeRange>
     */
    public function busyPeriods(CalendarAccount $account, array $calendarIds, TimeRange $window): Collection;

    /**
     * Write a booking to the account's calendar.
     */
    public function createEvent(CalendarAccount $account, string $calendarId, Booking $booking, bool $withOnlineMeeting): ExternalEvent;

    /**
     * Remove a previously written event.
     */
    public function deleteEvent(CalendarAccount $account, string $calendarId, string $eventId): void;
}
