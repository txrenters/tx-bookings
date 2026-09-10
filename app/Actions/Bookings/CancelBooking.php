<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Jobs\RemoveBookingFromCalendars;
use App\Models\Booking;
use App\Services\Activity\ActivityLogger;

class CancelBooking
{
    public function __construct(
        protected NotifyBookingParties $notify,
        protected ActivityLogger $activity,
    ) {
        //
    }

    /**
     * Call off a booking and tell everyone involved.
     */
    public function handle(Booking $booking, ?string $reason = null, string $canceledBy = 'invitee'): Booking
    {
        if (! $booking->status->isActive()) {
            return $booking;
        }

        $booking->update([
            'status' => BookingStatus::Canceled,
            'cancellation_reason' => $reason,
            'canceled_by' => $canceledBy,
            'canceled_at' => now(),
        ]);

        $booking->reminders()->whereNull('sent_at')->delete();
        $booking->automationRuns()->whereNull('sent_at')->delete();

        RemoveBookingFromCalendars::dispatch($booking);

        $booking->load(['eventType', 'host', 'hosts', 'guests']);

        $this->notify->canceled($booking);

        $this->activity->record(
            $booking->team,
            'booking.canceled',
            ($booking->eventType->name ?? 'A meeting').' with '.$booking->name.' was canceled',
            $booking,
            ['canceledBy' => $canceledBy, 'reason' => $reason],
        );

        return $booking;
    }
}
