<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\CalendarAccount;
use App\Models\User;
use App\Services\Calendar\CalendarProviderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncBookingToCalendars implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(public Booking $booking)
    {
        //
    }

    /**
     * Write the booking onto every host's connected calendar.
     */
    public function handle(CalendarProviderManager $providers): void
    {
        $booking = $this->booking->fresh(['eventType', 'hosts', 'guests', 'answers', 'host']);

        if ($booking === null || ! $booking->status->isActive()) {
            return;
        }

        $hosts = $booking->attendingHosts();
        $wantsOnlineMeeting = $booking->location_type->isGenerated();
        $meetingProvider = $booking->location_type->provider();

        foreach ($hosts as $host) {
            foreach ($this->accountsFor($host) as $account) {
                $calendar = $account->writeTarget();

                if ($calendar === null) {
                    continue;
                }

                try {
                    $event = $providers->for($account)->createEvent(
                        $account,
                        $calendar->external_id,
                        $booking,
                        // Only the provider that owns the meeting type generates a link.
                        $wantsOnlineMeeting && $account->provider === $meetingProvider,
                    );

                    $booking->calendarEvents()->updateOrCreate(
                        ['calendar_account_id' => $account->id],
                        [
                            'external_id' => $event->id,
                            'external_calendar_id' => $event->calendarId,
                            'meeting_url' => $event->meetingUrl,
                        ],
                    );

                    if (filled($event->meetingUrl) && blank($booking->meeting_url)) {
                        $booking->update(['meeting_url' => $event->meetingUrl]);
                    }

                    $account->update(['sync_error' => null]);
                } catch (Throwable $exception) {
                    $account->update(['sync_error' => $exception->getMessage()]);

                    Log::warning('Failed to write booking to calendar.', [
                        'booking_id' => $booking->id,
                        'calendar_account_id' => $account->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Get the calendar accounts a host has connected.
     *
     * @return Collection<int, CalendarAccount>
     */
    protected function accountsFor(User $host): Collection
    {
        return $host->calendarAccounts()->with('calendars')->get();
    }
}
