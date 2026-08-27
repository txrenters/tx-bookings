<?php

namespace App\Actions\Scheduling;

use App\Models\AvailabilitySchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateDefaultAvailability
{
    /**
     * Give a user weekday nine to five hours so their event types are bookable
     * from the moment they are created.
     */
    public function handle(User $user, ?string $timezone = null): AvailabilitySchedule
    {
        return DB::transaction(function () use ($user, $timezone) {
            $schedule = $user->availabilitySchedules()->create([
                'name' => 'Working hours',
                'timezone' => $timezone ?: ($user->timezone ?: config('scheduling.default_timezone')),
                'is_default' => true,
            ]);

            foreach (range(1, 5) as $dayOfWeek) {
                $schedule->rules()->create([
                    'day_of_week' => $dayOfWeek,
                    'starts_at' => '09:00:00',
                    'ends_at' => '17:00:00',
                ]);
            }

            return $schedule->fresh('rules');
        });
    }
}
