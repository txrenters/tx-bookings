<?php

namespace App\Models;

use Database\Factories\AvailabilityScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $team_id
 * @property string $name
 * @property string $timezone
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Team|null $team
 * @property-read Collection<int, AvailabilityRule> $rules
 * @property-read Collection<int, AvailabilityOverride> $overrides
 * @property-read Collection<int, EventType> $eventTypes
 */
#[Fillable(['user_id', 'team_id', 'name', 'timezone', 'is_default', 'is_active'])]
class AvailabilitySchedule extends Model
{
    /** @use HasFactory<AvailabilityScheduleFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AvailabilitySchedule $schedule) {
            if (empty($schedule->timezone)) {
                $schedule->timezone = config('scheduling.default_timezone');
            }
        });
    }

    /**
     * Get the user that owns the schedule.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the organization the schedule is shared across, if any.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Determine whether the schedule belongs to an organization rather than a
     * person. A shared schedule governs every host of an event type using it.
     */
    public function isShared(): bool
    {
        return $this->team_id !== null;
    }

    /**
     * Get the weekly rules for the schedule.
     *
     * @return HasMany<AvailabilityRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(AvailabilityRule::class);
    }

    /**
     * Get the event types governed by this schedule.
     *
     * @return HasMany<EventType, $this>
     */
    public function eventTypes(): HasMany
    {
        return $this->hasMany(EventType::class);
    }

    /**
     * Get the date specific overrides for the schedule.
     *
     * @return HasMany<AvailabilityOverride, $this>
     */
    public function overrides(): HasMany
    {
        return $this->hasMany(AvailabilityOverride::class);
    }

    /**
     * Summarise the weekly hours in a line, or null when there are none.
     *
     * Mirrors how the hours read on a booking page: "Weekdays, 10:30 am - 4 pm",
     * "Mon, Tue, Wed, 2 - 3:15 pm", or "Weekdays, hours vary".
     */
    public function summary(): ?string
    {
        $rules = $this->relationLoaded('rules') ? $this->rules : $this->rules()->get();

        if ($rules->isEmpty()) {
            return null;
        }

        $days = $rules->pluck('day_of_week')->unique()->sort()->values()->all();
        $times = $rules
            ->map(fn (AvailabilityRule $rule) => $rule->starts_at.$rule->ends_at)
            ->unique();

        if ($times->count() > 1) {
            return $this->describeDays($days).', hours vary';
        }

        /** @var AvailabilityRule $first */
        $first = $rules->first();

        return $this->describeDays($days).', '.$this->describeHours($first->starts_at, $first->ends_at);
    }

    /**
     * Describe a set of weekdays the way a person would say them.
     *
     * @param  array<int, int>  $days
     */
    protected function describeDays(array $days): string
    {
        if ($days === [1, 2, 3, 4, 5]) {
            return 'Weekdays';
        }

        if ($days === [0, 1, 2, 3, 4, 5, 6]) {
            return 'Every day';
        }

        if ($days === [0, 6]) {
            return 'Weekends';
        }

        $names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        // Read Monday first, the way a working week is written.
        usort($days, fn (int $a, int $b) => ($a === 0 ? 7 : $a) <=> ($b === 0 ? 7 : $b));

        return implode(', ', array_map(fn (int $day) => $names[$day], $days));
    }

    /**
     * Describe an hours range, dropping the meridiem when both ends share it.
     */
    protected function describeHours(string $startsAt, string $endsAt): string
    {
        $start = Carbon::createFromFormat('H:i:s', $startsAt);
        $end = Carbon::createFromFormat('H:i:s', $endsAt);

        $format = fn (Carbon $time) => $time->minute === 0
            ? $time->format('g a')
            : $time->format('g:i a');

        $startLabel = $start->format('a') === $end->format('a')
            ? rtrim(str_replace($start->format(' a'), '', $format($start)))
            : $format($start);

        return $startLabel.' - '.$format($end);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
