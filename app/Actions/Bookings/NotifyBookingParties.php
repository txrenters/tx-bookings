<?php

namespace App\Actions\Bookings;

use App\Models\Booking;
use App\Notifications\Bookings\BookingCanceled;
use App\Notifications\Bookings\BookingConfirmed;
use App\Notifications\Bookings\BookingDeclined;
use App\Notifications\Bookings\BookingPendingApproval;
use App\Notifications\Bookings\BookingRescheduled;
use Illuminate\Support\Facades\Notification;

class NotifyBookingParties
{
    /**
     * Tell everyone a booking has been made.
     */
    public function confirmed(Booking $booking): void
    {
        $this->send($booking, new BookingConfirmed($booking));
    }

    /**
     * Tell everyone a booking request awaits a host's approval.
     */
    public function pending(Booking $booking): void
    {
        $this->send($booking, new BookingPendingApproval($booking));
    }

    /**
     * Tell everyone a booking request was turned down.
     */
    public function declined(Booking $booking): void
    {
        $this->send($booking, new BookingDeclined($booking));
    }

    /**
     * Tell everyone a booking has been called off.
     */
    public function canceled(Booking $booking): void
    {
        $this->send($booking, new BookingCanceled($booking));
    }

    /**
     * Tell everyone a booking has moved.
     */
    public function rescheduled(Booking $booking, Booking $previous): void
    {
        $this->send($booking, new BookingRescheduled($booking, $previous));
    }

    /**
     * Deliver a notification to the invitee, their guests, and every host.
     */
    protected function send(Booking $booking, mixed $notification): void
    {
        Notification::send($booking->attendingHosts(), $notification);

        foreach ($booking->inviteeEmails() as $email) {
            Notification::route('mail', $email)->notify($notification);
        }
    }
}
