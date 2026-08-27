<?php

namespace App\Models;

use App\Enums\LimitPeriod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A cap on how many meetings a host will take in a period, across every
 * event type they host.
 *
 * @property int $id
 * @property int $user_id
 * @property LimitPeriod $period
 * @property int $max_bookings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'period', 'max_bookings'])]
class MeetingLimit extends Model
{
    /**
     * Get the user the limit belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => LimitPeriod::class,
        ];
    }
}
