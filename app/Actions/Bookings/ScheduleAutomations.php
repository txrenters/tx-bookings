<?php

namespace App\Actions\Bookings;

use App\Models\Automation;
use App\Models\Booking;

class ScheduleAutomations
{
    /**
     * Queue every automation that covers this booking.
     *
     * Called when a booking is made and again when it moves, so a "before it
     * starts" message follows the meeting rather than the old time. Anything
     * already sent stays sent: rescheduling is not a reason to send it twice.
     */
    public function handle(Booking $booking): void
    {
        $booking->loadMissing('eventType');

        $automations = Automation::query()
            ->where('team_id', $booking->team_id)
            ->where('is_active', true)
            ->with('eventTypes:id')
            ->get()
            ->filter(fn (Automation $automation) => $automation->covers($booking->eventType));

        // Pending work is rebuilt from scratch; sent work is left alone.
        $booking->automationRuns()->whereNull('sent_at')->delete();

        foreach ($automations as $automation) {
            if ($booking->automationRuns()->where('automation_id', $automation->id)->whereNotNull('sent_at')->exists()) {
                continue;
            }

            $sendAt = $automation->sendAtFor($booking);

            if ($sendAt === null) {
                continue;
            }

            $booking->automationRuns()->create([
                'automation_id' => $automation->id,
                'send_at' => $sendAt,
            ]);
        }
    }
}
