<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Jobs\SyncBookingToCalendars;
use App\Models\Booking;
use App\Models\User;
use App\Services\Activity\ActivityLogger;

class ApproveBooking
{
    public function __construct(
        protected ScheduleReminders $scheduleReminders,
        protected NotifyBookingParties $notify,
        protected ActivityLogger $activity,
    ) {
        //
    }

    /**
     * Confirm a pending booking request and tell everyone involved.
     */
    public function handle(Booking $booking, User $actor): Booking
    {
        if ($booking->status !== BookingStatus::Pending) {
            return $booking;
        }

        $booking->update(['status' => BookingStatus::Confirmed]);

        $this->scheduleReminders->handle($booking);

        $booking->load(['eventType', 'host', 'hosts', 'guests', 'answers']);

        SyncBookingToCalendars::dispatch($booking);

        $this->notify->confirmed($booking);

        $this->activity->record(
            $booking->team,
            'booking.approved',
            $actor->name.' approved '.($booking->eventType->name ?? 'a meeting').' with '.$booking->name,
            $booking,
            ['startsAt' => $booking->starts_at->toIso8601String()],
            $actor,
        );

        return $booking;
    }
}
