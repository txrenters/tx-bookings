<?php

namespace App\Models;

use Database\Factories\LeavePeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A stretch of time a user is away, detected from their mailbox's automatic
 * reply or entered by hand.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $calendar_account_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read CalendarAccount|null $calendarAccount
 */
#[Fillable(['user_id', 'calendar_account_id', 'starts_at', 'ends_at', 'message'])]
class LeavePeriod extends Model
{
    /** @use HasFactory<LeavePeriodFactory> */
    use HasFactory;

    /**
     * Get the user who is away.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the calendar account whose mailbox reported the leave.
     *
     * @return BelongsTo<CalendarAccount, $this>
     */
    public function calendarAccount(): BelongsTo
    {
        return $this->belongsTo(CalendarAccount::class);
    }

    /**
     * Scope the query to leave that covers the given moment.
     *
     * @param  Builder<LeavePeriod>  $query
     */
    public function scopeCovering(Builder $query, mixed $moment): void
    {
        $query->where('starts_at', '<=', $moment)->where('ends_at', '>', $moment);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
