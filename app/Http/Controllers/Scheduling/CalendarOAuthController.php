<?php

namespace App\Http\Controllers\Scheduling;

use App\Data\Calendar\ExternalCalendar;
use App\Enums\CalendarProvider;
use App\Http\Controllers\Controller;
use App\Jobs\SyncCalendarBusyBlocks;
use App\Models\CalendarAccount;
use App\Services\Calendar\CalendarProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Throwable;

class CalendarOAuthController extends Controller
{
    public function __construct(protected CalendarProviderManager $providers)
    {
        //
    }

    /**
     * Send the user off to the provider's consent screen.
     */
    public function redirect(Request $request, CalendarProvider $provider): RedirectResponse
    {
        abort_unless($provider->isConfigured(), 404);

        $state = Str::random(40);

        $request->session()->put('calendar_oauth_state', $state);

        return redirect()->away($this->providers->driver($provider)->authorizationUrl($state));
    }

    /**
     * Handle the provider redirecting back with an authorization code.
     */
    public function callback(Request $request, CalendarProvider $provider): RedirectResponse
    {
        abort_unless($provider->isConfigured(), 404);

        $expectedState = $request->session()->pull('calendar_oauth_state');

        if (blank($request->query('code')) || $request->query('state') !== $expectedState) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Could not connect that calendar. Please try again.')]);

            return to_route('availability.calendars');
        }

        $driver = $this->providers->driver($provider);

        try {
            $identity = $driver->exchangeCode($request->string('code')->toString());

            $account = CalendarAccount::updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'provider' => $provider,
                    'external_id' => $identity->externalId,
                ],
                [
                    'email' => $identity->email,
                    'access_token' => $identity->accessToken,
                    'refresh_token' => $identity->refreshToken,
                    'token_expires_at' => $identity->expiresAt,
                    'sync_error' => null,
                ],
            );

            $this->storeCalendars($account, $driver->listCalendars($account));

            SyncCalendarBusyBlocks::dispatch($account);
        } catch (Throwable $exception) {
            report($exception);

            Inertia::flash('toast', ['type' => 'error', 'message' => __('Could not connect that calendar. Please try again.')]);

            return to_route('availability.calendars');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calendar connected.')]);

        return to_route('availability.calendars');
    }

    /**
     * Persist the calendars discovered on a freshly connected account.
     *
     * @param  Collection<int, ExternalCalendar>  $calendars
     */
    protected function storeCalendars(CalendarAccount $account, $calendars): void
    {
        $hasWriteTarget = $account->calendars()->where('is_write_target', true)->exists();

        foreach ($calendars as $calendar) {
            $account->calendars()->updateOrCreate(
                ['external_id' => $calendar->id],
                [
                    'name' => $calendar->name,
                    'timezone' => $calendar->timezone,
                    'is_primary' => $calendar->isPrimary,
                    // Default to watching everything, and writing to the primary.
                    'checks_conflicts' => true,
                    'is_write_target' => ! $hasWriteTarget && $calendar->isPrimary,
                ],
            );
        }
    }
}
