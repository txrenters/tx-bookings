<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\SlotUnavailableException;
use App\Jobs\RemoveBookingFromCalendars;
use App\Models\Booking;
use App\Services\Activity\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RescheduleBooking
{
    public function __construct(
        protected CreateBooking $createBooking,
        protected NotifyBookingParties $notify,
        protected ActivityLogger $activity,
    ) {
        //
    }

    /**
     * Move a booking to a new time by replacing it with a fresh one.
     *
     * The original is kept, marked as rescheduled, so the history of the
     * meeting survives and the invitee's old links resolve to something.
     *
     * @throws SlotUnavailableException
     */
    public function handle(Booking $booking, CarbonImmutable $startsAt, ?string $reason = null): Booking
    {
        $replacement = $this->createBooking->handle(
            $booking->eventType,
            $startsAt,
            [
                'name' => $booking->name,
                'email' => $booking->email,
                'notes' => $booking->notes,
                'timezone' => $booking->invitee_timezone,
                'location_detail' => $booking->location_detail,
                'guests' => $booking->guests->pluck('email')->all(),
            ],
            replacing: $booking,
        );

        DB::transaction(function () use ($booking, $replacement, $reason) {
            $booking->update([
                'status' => BookingStatus::Rescheduled,
                'cancellation_reason' => $reason,
                'canceled_by' => 'invitee',
                'canceled_at' => now(),
            ]);

            $booking->reminders()->whereNull('sent_at')->delete();

            foreach ($booking->answers as $answer) {
                $replacement->answers()->create([
                    'event_type_question_id' => $answer->event_type_question_id,
                    'label' => $answer->label,
                    'answer' => $answer->answer,
                ]);
            }
        });

        RemoveBookingFromCalendars::dispatch($booking);

        /*
         * A replacement on an approval-required event type is pending again,
         * so announcing it as rescheduled would promise a time no host has
         * agreed to (and attach a cancellation ICS, since the ICS method
         * follows the booking's non-confirmed status).
         */
        $replacement = $replacement->fresh(['eventType', 'host', 'hosts', 'guests']);

        $replacement->status->isConfirmed()
            ? $this->notify->rescheduled($replacement, $booking)
            : $this->notify->pending($replacement);

        $this->activity->record(
            $replacement->team,
            'booking.rescheduled',
            ($replacement->eventType->name ?? 'A meeting').' with '.$replacement->name.' was moved',
            $replacement,
            [
                'from' => $booking->starts_at->toIso8601String(),
                'to' => $replacement->starts_at->toIso8601String(),
            ],
        );

        return $replacement;
    }
}
