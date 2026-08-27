<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property int|null $event_type_question_id
 * @property string $label
 * @property string|null $answer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Booking $booking
 * @property-read EventTypeQuestion|null $question
 */
#[Fillable(['booking_id', 'event_type_question_id', 'label', 'answer'])]
class BookingAnswer extends Model
{
    /**
     * Get the booking the answer belongs to.
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Get the question that was answered.
     *
     * @return BelongsTo<EventTypeQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(EventTypeQuestion::class, 'event_type_question_id');
    }
}
