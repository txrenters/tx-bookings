<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Models\Booking;
use App\Models\EventType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDay()->startOfHour();

        return [
            'event_type_id' => EventType::factory(),
            'team_id' => fn (array $attributes) => EventType::query()
                ->whereKey($attributes['event_type_id'])
                ->value('team_id'),
            'user_id' => fn (array $attributes) => EventType::query()
                ->whereKey($attributes['event_type_id'])
                ->value('user_id'),
            'status' => BookingStatus::Confirmed,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'invitee_timezone' => 'UTC',
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'location_type' => LocationType::CustomLink,
            'location_detail' => 'https://example.com/meet',
        ];
    }

    /**
     * Indicate that the booking still awaits a host's approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Pending,
        ]);
    }

    /**
     * Indicate that the booking was canceled.
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Canceled,
            'canceled_at' => now(),
        ]);
    }
}
