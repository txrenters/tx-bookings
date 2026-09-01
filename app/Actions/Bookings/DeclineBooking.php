<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Services\Activity\ActivityLogger;

class DeclineBooking
{
    public function __construct(
        protected NotifyBookingParties $notify,
        protected ActivityLogger $activity,
    ) {
        //
    }

    /**
     * Turn down a pending booking request and tell everyone involved.
     *
     * This deliberately does not delegate to CancelBooking: a declined request
     * was never synced to any calendar, so there is no removal job to dispatch
     * and no cancellation ICS to send — the invitee gets a declined email.
     */
    public function handle(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        if ($booking->status !== BookingStatus::Pending) {
            return $booking;
        }

        $booking->update([
            'status' => BookingStatus::Canceled,
            'cancellation_reason' => $reason,
            'canceled_by' => 'host',
            'canceled_at' => now(),
        ]);

        $booking->reminders()->whereNull('sent_at')->delete();

        $booking->load(['eventType', 'host', 'hosts', 'guests']);

        $this->notify->declined($booking);

        $this->activity->record(
            $booking->team,
            'booking.declined',
            $actor->name.' declined '.($booking->eventType?->name ?? 'a meeting').' with '.$booking->name,
            $booking,
            ['reason' => $reason],
            $actor,
        );

        return $booking;
    }
}
