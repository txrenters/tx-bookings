<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\Calendar\CalendarProviderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RemoveBookingFromCalendars implements ShouldQueue
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
     * Delete the external events previously written for the booking.
     */
    public function handle(CalendarProviderManager $providers): void
    {
        $events = $this->booking->calendarEvents()->with('account')->get();

        foreach ($events as $event) {
            $account = $event->account;

            try {
                $providers->for($account)->deleteEvent($account, $event->external_calendar_id, $event->external_id);

                $event->delete();
            } catch (Throwable $exception) {
                Log::warning('Failed to remove booking from calendar.', [
                    'booking_id' => $this->booking->id,
                    'calendar_account_id' => $account->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
