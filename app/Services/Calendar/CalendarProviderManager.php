<?php

namespace App\Services\Calendar;

use App\Enums\CalendarProvider;
use App\Models\CalendarAccount;
use App\Services\Calendar\Providers\GoogleCalendarProvider;
use App\Services\Calendar\Providers\MicrosoftCalendarProvider;
use Illuminate\Contracts\Container\Container;

class CalendarProviderManager
{
    public function __construct(protected Container $container)
    {
        //
    }

    /**
     * Resolve the driver for a provider.
     */
    public function driver(CalendarProvider $provider): CalendarProviderContract
    {
        return $this->container->make(match ($provider) {
            CalendarProvider::Google => GoogleCalendarProvider::class,
            CalendarProvider::Microsoft => MicrosoftCalendarProvider::class,
        });
    }

    /**
     * Resolve the driver for an account, refreshing its token when needed.
     */
    public function for(CalendarAccount $account): CalendarProviderContract
    {
        $driver = $this->driver($account->provider);

        if ($account->tokenHasExpired()) {
            $driver->refreshToken($account);
            $account->refresh();
        }

        return $driver;
    }
}
