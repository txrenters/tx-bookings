<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property int $minutes_before
 * @property Carbon $send_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Booking $booking
 */
#[Fillable(['booking_id', 'minutes_before', 'send_at', 'sent_at'])]
class BookingReminder extends Model
{
    /**
     * Get the booking the reminder belongs to.
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Scope the query to reminders that are ready to be delivered.
     *
     * @param  Builder<BookingReminder>  $query
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
