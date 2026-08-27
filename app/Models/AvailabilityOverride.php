<?php

namespace App\Models;

use App\Concerns\NormalisesWallClockTimes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $availability_schedule_id
 * @property Carbon $date
 * @property string|null $starts_at
 * @property string|null $ends_at
 * @property bool $is_unavailable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AvailabilitySchedule $schedule
 */
#[Fillable(['availability_schedule_id', 'date', 'starts_at', 'ends_at', 'is_unavailable'])]
class AvailabilityOverride extends Model
{
    use NormalisesWallClockTimes;

    /**
     * Get the schedule the override belongs to.
     *
     * @return BelongsTo<AvailabilitySchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AvailabilitySchedule::class, 'availability_schedule_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_unavailable' => 'boolean',
        ];
    }
}
