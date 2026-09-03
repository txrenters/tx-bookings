<?php

namespace App\Http\Controllers\Scheduling;

use App\Actions\Scheduling\SaveAvailabilitySchedule;
use App\Enums\CalendarProvider;
use App\Enums\LimitPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\SaveAvailabilityScheduleRequest;
use App\Http\Requests\Scheduling\UpdateAdvancedAvailabilityRequest;
use App\Models\AvailabilityOverride;
use App\Models\AvailabilityRule;
use App\Models\AvailabilitySchedule;
use App\Models\Calendar;
use App\Models\CalendarAccount;
use App\Models\MeetingLimit;
use App\Models\Team;
use App\Services\Scheduling\HolidayCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AvailabilityScheduleController extends Controller
{
    /**
     * Display the user's availability schedules.
     */
    public function index(Request $request, Team $current_team): Response
    {
        $schedules = $request->user()
            ->availabilitySchedules()
            ->with(['rules', 'overrides'])
            ->withCount('eventTypes')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return Inertia::render('scheduling/availability/Index', [
            'schedules' => $schedules->map(fn (AvailabilitySchedule $schedule) => $this->toPayload($schedule)),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    /**
     * Show which calendars are checked for conflicts and written to.
     */
    public function calendars(Request $request, Team $current_team): Response
    {
        $accounts = $request->user()
            ->calendarAccounts()
            ->with('calendars')
            ->orderBy('provider')
            ->get();

        return Inertia::render('scheduling/availability/Calendars', [
            'providers' => array_map(fn (CalendarProvider $provider) => [
                'value' => $provider->value,
                'label' => $provider->label(),
                'isConfigured' => $provider->isConfigured(),
                'isConnected' => $accounts->contains(
                    fn (CalendarAccount $account) => $account->provider === $provider,
                ),
            ], CalendarProvider::cases()),
            'accounts' => $accounts->map(fn (CalendarAccount $account) => [
                'id' => $account->id,
                'provider' => $account->provider->value,
                'providerLabel' => $account->provider->label(),
                'email' => $account->email,
                'lastSyncedAt' => $account->last_synced_at?->toIso8601String(),
                'syncError' => $account->sync_error,
                'calendars' => $account->calendars->map(fn (Calendar $calendar) => [
                    'id' => $calendar->id,
                    'name' => $calendar->name,
                    'isPrimary' => $calendar->is_primary,
                    'checksConflicts' => $calendar->checks_conflicts,
                    'isWriteTarget' => $calendar->is_write_target,
                ])->values(),
            ]),
        ]);
    }

    /**
     * Show the settings that govern the user's public booking page.
     */
    public function advanced(Request $request, Team $current_team, HolidayCalendar $holidays): Response
    {
        $user = $request->user();
        $country = $user->holiday_country;

        return Inertia::render('scheduling/availability/Advanced', [
            'limits' => $user->meetingLimits()->get()
                ->map(fn (MeetingLimit $limit) => [
                    'period' => $limit->period->value,
                    'max_bookings' => $limit->max_bookings,
                ])->values(),
            'periods' => LimitPeriod::options(),
            'holidayCountry' => $country,
            'countries' => $holidays->countries(),
            'holidays' => blank($country) ? [] : $holidays->forCountry($country),
            'enabledHolidays' => $user->enabledHolidays(),
        ]);
    }

    /**
     * Save the advanced settings.
     */
    public function updateAdvanced(UpdateAdvancedAvailabilityRequest $request, Team $current_team): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $user) {
            $user->update(['holiday_country' => $request->validated('holiday_country')]);

            $user->meetingLimits()->delete();

            foreach ($request->validated('limits', []) as $limit) {
                $user->meetingLimits()->create([
                    'period' => $limit['period'],
                    'max_bookings' => $limit['max_bookings'],
                ]);
            }

            DB::table('user_holidays')->where('user_id', $user->id)->delete();

            foreach (array_unique($request->validated('holidays', [])) as $key) {
                DB::table('user_holidays')->insert([
                    'user_id' => $user->id,
                    'holiday_key' => $key,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return to_route('availability.advanced', ['current_team' => $current_team->slug]);
    }

    /**
     * Store a new schedule.
     */
    public function store(SaveAvailabilityScheduleRequest $request, Team $current_team, SaveAvailabilitySchedule $saveSchedule): RedirectResponse
    {
        Gate::authorize('create', AvailabilitySchedule::class);

        $saveSchedule->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schedule created.')]);

        return to_route('availability.index', ['current_team' => $current_team->slug]);
    }

    /**
     * Update a schedule.
     */
    public function update(
        SaveAvailabilityScheduleRequest $request,
        Team $current_team,
        AvailabilitySchedule $availability,
        SaveAvailabilitySchedule $saveSchedule,
    ): RedirectResponse {
        Gate::authorize('update', $availability);

        $saveSchedule->handle($request->user(), $request->validated(), $availability);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Availability updated.')]);

        return to_route('availability.index', ['current_team' => $current_team->slug]);
    }

    /**
     * Delete a schedule.
     */
    public function destroy(Team $current_team, AvailabilitySchedule $availability): RedirectResponse
    {
        Gate::authorize('delete', $availability);

        $availability->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schedule deleted.')]);

        return to_route('availability.index', ['current_team' => $current_team->slug]);
    }

    /**
     * Present a schedule for the availability screen.
     *
     * @return array<string, mixed>
     */
    protected function toPayload(AvailabilitySchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'timezone' => $schedule->timezone,
            'isDefault' => $schedule->is_default,
            'isActive' => $schedule->is_active,
            'eventTypeCount' => $schedule->event_types_count,
            'summary' => $schedule->summary(),
            'rules' => $schedule->rules->map(fn (AvailabilityRule $rule) => [
                'day_of_week' => $rule->day_of_week,
                'starts_at' => substr($rule->starts_at, 0, 5),
                'ends_at' => substr($rule->ends_at, 0, 5),
            ])->values(),
            'overrides' => $schedule->overrides->map(fn (AvailabilityOverride $override) => [
                'date' => $override->date->toDateString(),
                'is_unavailable' => $override->is_unavailable,
                'starts_at' => $override->starts_at ? substr($override->starts_at, 0, 5) : null,
                'ends_at' => $override->ends_at ? substr($override->ends_at, 0, 5) : null,
            ])->values(),
        ];
    }
}
