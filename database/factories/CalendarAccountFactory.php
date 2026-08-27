<?php

namespace Database\Factories;

use App\Enums\CalendarProvider;
use App\Models\CalendarAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarAccount>
 */
class CalendarAccountFactory extends Factory
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
            'provider' => CalendarProvider::Google,
            'external_id' => fake()->uuid(),
            'email' => fake()->unique()->safeEmail(),
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
        ];
    }

    /**
     * Use the Microsoft provider.
     */
    public function microsoft(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => CalendarProvider::Microsoft,
        ]);
    }

    /**
     * Indicate that the stored access token has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'token_expires_at' => now()->subHour(),
        ]);
    }
}
