<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Jobs\SyncCalendarBusyBlocks;
use App\Jobs\SyncLeavePeriods;
use App\Models\Calendar;
use App\Models\CalendarAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalendarAccountController extends Controller
{
    /**
     * Update how a calendar participates in scheduling.
     */
    public function updateCalendar(Request $request, Calendar $calendar): RedirectResponse
    {
        abort_unless($calendar->account->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'checks_conflicts' => ['boolean'],
            'is_write_target' => ['boolean'],
        ]);

        if (($validated['is_write_target'] ?? false) === true) {
            Calendar::query()
                ->where('calendar_account_id', $calendar->calendar_account_id)
                ->whereKeyNot($calendar->id)
                ->update(['is_write_target' => false]);
        }

        $calendar->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calendar updated.')]);

        return back();
    }

    /**
     * Pull busy times and out of office replies again for an account.
     */
    public function sync(Request $request, CalendarAccount $calendarAccount): RedirectResponse
    {
        abort_unless($calendarAccount->user_id === $request->user()->id, 403);

        SyncCalendarBusyBlocks::dispatch($calendarAccount);
        SyncLeavePeriods::dispatch($calendarAccount);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Syncing calendar in the background.')]);

        return back();
    }

    /**
     * Disconnect a calendar account.
     */
    public function destroy(Request $request, CalendarAccount $calendarAccount): RedirectResponse
    {
        abort_unless($calendarAccount->user_id === $request->user()->id, 403);

        $calendarAccount->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calendar disconnected.')]);

        return back();
    }
}
