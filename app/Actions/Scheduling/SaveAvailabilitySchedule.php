<?php

namespace App\Actions\Scheduling;

use App\Models\AvailabilitySchedule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveAvailabilitySchedule
{
    /**
     * Create or update a schedule together with its rules and overrides.
     *
     * The owner is a person for personal hours, or an organization for hours
     * shared by whoever hosts an event type.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User|Team $owner, array $attributes, ?AvailabilitySchedule $schedule = null): AvailabilitySchedule
    {
        return DB::transaction(function () use ($owner, $attributes, $schedule) {
            $rules = $attributes['rules'] ?? [];
            $overrides = $attributes['overrides'] ?? [];

            unset($attributes['rules'], $attributes['overrides']);

            $isDefault = (bool) ($attributes['is_default'] ?? false);

            if ($schedule === null) {
                $schedule = $owner->availabilitySchedules()->create($attributes);
            } else {
                $schedule->update($attributes);
            }

            // One default per owner: an organization's shared schedules have
            // their own, separate from anyone's personal default.
            if ($isDefault) {
                $owner->availabilitySchedules()
                    ->whereKeyNot($schedule->id)
                    ->update(['is_default' => false]);
            }

            $schedule->rules()->delete();

            foreach ($rules as $rule) {
                $schedule->rules()->create([
                    'day_of_week' => (int) $rule['day_of_week'],
                    'starts_at' => $rule['starts_at'].':00',
                    'ends_at' => $rule['ends_at'].':00',
                ]);
            }

            $schedule->overrides()->delete();

            foreach ($overrides as $override) {
                $schedule->overrides()->create([
                    'date' => $override['date'],
                    'is_unavailable' => (bool) ($override['is_unavailable'] ?? false),
                    'starts_at' => filled($override['starts_at'] ?? null) ? $override['starts_at'].':00' : null,
                    'ends_at' => filled($override['ends_at'] ?? null) ? $override['ends_at'].':00' : null,
                ]);
            }

            return $schedule->fresh(['rules', 'overrides']);
        });
    }
}
