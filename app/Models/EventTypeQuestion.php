<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_type_id
 * @property QuestionType $type
 * @property string $label
 * @property string|null $help_text
 * @property array<int, string>|null $options
 * @property bool $is_required
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EventType $eventType
 */
#[Fillable(['event_type_id', 'type', 'label', 'help_text', 'options', 'is_required', 'position'])]
class EventTypeQuestion extends Model
{
    /**
     * Get the event type the question belongs to.
     *
     * @return BelongsTo<EventType, $this>
     */
    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }
}
