<?php

namespace App\Actions\Bookings;

use App\Models\Automation;
use App\Models\AutomationRun;
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
            ->active()
            ->with('eventTypes:id')
            ->get()
            ->filter(fn (Automation $automation) => $automation->covers($booking->eventType));

        // Pending work is rebuilt from scratch; sent work is left alone.
        $booking->automationRuns()->whereNull('sent_at')->delete();

        $alreadySent = $this->sentAutomationIds($booking);

        foreach ($automations as $automation) {
            if (in_array($automation->id, $alreadySent, true)) {
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

    /**
     * Get the automations that have already gone out about this meeting.
     *
     * A reschedule replaces the booking row rather than moving it, so what was
     * sent is recorded against the booking this one replaced. Walk that chain,
     * or a meeting moved twice would announce itself twice.
     *
     * @return array<int, int>
     */
    protected function sentAutomationIds(Booking $booking): array
    {
        $bookingIds = [$booking->id];
        $previousId = $booking->rescheduled_from_id;

        while ($previousId !== null && ! in_array($previousId, $bookingIds, true)) {
            $bookingIds[] = $previousId;
            $previousId = Booking::query()->whereKey($previousId)->value('rescheduled_from_id');
        }

        return AutomationRun::query()
            ->whereIn('booking_id', $bookingIds)
            ->whereNotNull('sent_at')
            ->pluck('automation_id')
            ->all();
    }
}
