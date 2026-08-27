<?php

namespace App\Actions\Bookings;

use App\Models\Booking;

class ScheduleReminders
{
    /**
     * Queue up the reminder rows for a booking.
     */
    public function handle(Booking $booking): void
    {
        $booking->reminders()->delete();

        /** @var array<int, int> $leadTimes */
        $leadTimes = config('scheduling.reminder_lead_times', []);

        foreach ($leadTimes as $minutesBefore) {
            $sendAt = $booking->starts_at->copy()->subMinutes($minutesBefore);

            if ($sendAt->isPast()) {
                continue;
            }

            $booking->reminders()->create([
                'minutes_before' => $minutesBefore,
                'send_at' => $sendAt,
            ]);
        }
    }
}
