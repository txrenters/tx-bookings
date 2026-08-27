<?php

namespace App\Actions\Scheduling;

use App\Models\User;
use App\Services\Scheduling\HolidayCalendar;
use Illuminate\Support\Facades\DB;

class ApplyDefaultHolidays
{
    public function __construct(protected HolidayCalendar $holidays)
    {
        //
    }

    /**
     * Opt a user in to their country's public holidays.
     *
     * Being unavailable on a public holiday is the expectation rather than the
     * exception, so this is on from the start and turned off per holiday.
     */
    public function handle(User $user, ?string $country = null): void
    {
        $country ??= config('scheduling.default_holiday_country');

        if (blank($country)) {
            return;
        }

        $holidays = $this->holidays->forCountry($country);

        if ($holidays->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($user, $country, $holidays) {
            $user->update(['holiday_country' => $country]);

            $now = now();

            DB::table('user_holidays')->upsert(
                $holidays->map(fn (array $holiday) => [
                    'user_id' => $user->id,
                    'holiday_key' => $holiday['key'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
                ['user_id', 'holiday_key'],
                ['updated_at'],
            );
        });
    }
}
