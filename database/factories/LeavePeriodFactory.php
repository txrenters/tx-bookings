<?php

namespace Database\Factories;

use App\Models\LeavePeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeavePeriod>
 */
class LeavePeriodFactory extends Factory
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
            'calendar_account_id' => null,
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addWeek()->endOfDay(),
            'message' => 'I am out of the office.',
        ];
    }
}
