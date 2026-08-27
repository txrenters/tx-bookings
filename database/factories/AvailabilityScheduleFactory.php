<?php

namespace Database\Factories;

use App\Models\AvailabilitySchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilitySchedule>
 */
class AvailabilityScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Working hours',
            'timezone' => 'UTC',
            'is_default' => true,
        ];
    }

    /**
     * Give the schedule weekday hours in its own timezone.
     */
    public function weekdays(string $startsAt = '09:00:00', string $endsAt = '17:00:00'): static
    {
        return $this->afterCreating(function (AvailabilitySchedule $schedule) use ($startsAt, $endsAt) {
            foreach (range(1, 5) as $dayOfWeek) {
                $schedule->rules()->create([
                    'day_of_week' => $dayOfWeek,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]);
            }
        });
    }

    /**
     * Give the schedule the same hours every day of the week.
     */
    public function everyDay(string $startsAt = '09:00:00', string $endsAt = '17:00:00'): static
    {
        return $this->afterCreating(function (AvailabilitySchedule $schedule) use ($startsAt, $endsAt) {
            foreach (range(0, 6) as $dayOfWeek) {
                $schedule->rules()->create([
                    'day_of_week' => $dayOfWeek,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]);
            }
        });
    }

    /**
     * Set the timezone the schedule's hours are written in.
     */
    public function timezone(string $timezone): static
    {
        return $this->state(fn (array $attributes) => [
            'timezone' => $timezone,
        ]);
    }
}
