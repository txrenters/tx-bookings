<?php

namespace App\Models;

use App\Enums\AutomationRecipient;
use App\Enums\AutomationTrigger;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A rule that sends an email when something happens to a booking.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property AutomationTrigger $trigger
 * @property int|null $offset_minutes
 * @property AutomationRecipient $recipient
 * @property string|null $recipient_email
 * @property string $subject
 * @property string $body
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Collection<int, EventType> $eventTypes
 * @property-read Collection<int, AutomationRun> $runs
 */
#[Fillable([
    'team_id', 'name', 'trigger', 'offset_minutes', 'recipient',
    'recipient_email', 'subject', 'body', 'is_active',
])]
class Automation extends Model
{
    /**
     * Get the organization the automation belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the event types the automation is limited to.
     *
     * None means every event type in the organization.
     *
     * @return BelongsToMany<EventType, $this>
     */
    public function eventTypes(): BelongsToMany
    {
        return $this->belongsToMany(EventType::class, 'automation_event_type');
    }

    /**
     * Get the sends this automation has queued.
     *
     * @return HasMany<AutomationRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    /**
     * Determine whether the automation covers the given event type.
     */
    public function covers(EventType $eventType): bool
    {
        return $this->eventTypes->isEmpty()
            || $this->eventTypes->contains('id', $eventType->id);
    }

    /**
     * Work out when this automation should fire for a booking, or null when
     * that moment has already passed.
     */
    public function sendAtFor(Booking $booking): ?CarbonInterface
    {
        $sendAt = match ($this->trigger) {
            // A delay after booking is allowed: "24 hours after it is booked".
            AutomationTrigger::Booked => now()->addMinutes($this->offset_minutes ?? 0),
            AutomationTrigger::BeforeStart => $booking->starts_at->copy()->subMinutes($this->offset_minutes ?? 0),
            AutomationTrigger::AfterEnd => $booking->ends_at->copy()->addMinutes($this->offset_minutes ?? 0),
        };

        // A meeting booked for later today can already be past its "an hour
        // before" moment; there is nothing sensible to send then.
        return $sendAt->isPast() && $this->trigger !== AutomationTrigger::Booked
            ? null
            : $sendAt;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger' => AutomationTrigger::class,
            'recipient' => AutomationRecipient::class,
            'is_active' => 'boolean',
        ];
    }
}
