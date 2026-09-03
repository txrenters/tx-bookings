<?php

namespace App\Actions\Scheduling;

use App\Models\EventType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DuplicateEventType
{
    /**
     * Copy an event type along with its questions and host pool.
     *
     * The copy keeps the original's owner, group, and active state — the
     * "(copy)" name makes it distinguishable, and everything is editable
     * afterwards. Host pivot rows are copied directly because they carry a
     * per-host availability_schedule_id as well as the priority.
     */
    public function handle(EventType $eventType): EventType
    {
        return DB::transaction(function () use ($eventType) {
            $eventType->loadMissing(['hosts', 'questions']);

            $copy = $eventType->replicate();
            $copy->name = Str::limit($eventType->name, 248, '').' (copy)';
            $copy->slug = EventType::generateUniqueSlug($eventType->slug, $eventType->team_id);
            $copy->save();

            foreach ($eventType->hosts as $host) {
                $copy->hosts()->attach($host->id, [
                    'availability_schedule_id' => $host->pivot?->getAttribute('availability_schedule_id'),
                    'priority' => $host->pivot?->getAttribute('priority'),
                ]);
            }

            foreach ($eventType->questions as $question) {
                $copy->questions()->create(
                    $question->only(['type', 'label', 'help_text', 'options', 'is_required', 'position']),
                );
            }

            return $copy->fresh(['hosts', 'questions']);
        });
    }
}
