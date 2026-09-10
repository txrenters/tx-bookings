<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One automation, queued against one booking.
 *
 * @property int $id
 * @property int $automation_id
 * @property int $booking_id
 * @property Carbon $send_at
 * @property Carbon|null $sent_at
 * @property string|null $failure
 * @property-read Automation $automation
 * @property-read Booking $booking
 */
#[Fillable(['automation_id', 'booking_id', 'send_at', 'sent_at', 'failure'])]
class AutomationRun extends Model
{
    /**
     * Get the rule that queued this.
     *
     * @return BelongsTo<Automation, $this>
     */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    /**
     * Get the booking it is about.
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Scope the query to runs that are ready to be delivered.
     *
     * @param  Builder<AutomationRun>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->whereNull('sent_at')->where('send_at', '<=', now());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'send_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
