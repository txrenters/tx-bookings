<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property int $calendar_account_id
 * @property string $external_id
 * @property string $external_calendar_id
 * @property string|null $meeting_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Booking $booking
 * @property-read CalendarAccount $account
 */
#[Fillable(['booking_id', 'calendar_account_id', 'external_id', 'external_calendar_id', 'meeting_url'])]
class BookingCalendarEvent extends Model
{
    /**
     * Get the booking the external event mirrors.
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Get the calendar account the event lives on.
     *
     * @return BelongsTo<CalendarAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CalendarAccount::class, 'calendar_account_id');
    }
}
