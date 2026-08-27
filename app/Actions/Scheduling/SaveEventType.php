<?php

namespace App\Actions\Scheduling;

use App\Models\EventType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveEventType
{
    /**
     * Create or update an event type along with its hosts and questions.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $owner, Team $team, array $attributes, ?EventType $eventType = null): EventType
    {
        return DB::transaction(function () use ($owner, $team, $attributes, $eventType) {
            $hostIds = $attributes['host_ids'] ?? null;
            $questions = $attributes['questions'] ?? null;

            unset($attributes['host_ids'], $attributes['questions'], $attributes['user_id']);

            // A group is the source of truth for the pool, so pinned hosts are
            // cleared to avoid two competing answers.
            if (filled($attributes['group_id'] ?? null)) {
                $hostIds = [];
            }

            if ($eventType === null) {
                $eventType = new EventType($attributes);
                $eventType->team_id = $team->id;
                $eventType->user_id = $owner->id;
                $eventType->save();
            } else {
                // Ownership can move, which is how an admin hands a one-on-one
                // to the member who should actually host it.
                $eventType->fill($attributes);
                $eventType->user_id = $owner->id;
                $eventType->save();
            }

            if ($hostIds !== null) {
                $this->syncHosts($eventType, $hostIds);
            }

            if ($questions !== null) {
                $this->syncQuestions($eventType, $questions);
            }

            return $eventType->fresh(['hosts', 'questions']);
        });
    }

    /**
     * Sync the pooled hosts, preserving the order they were given in.
     *
     * @param  array<int, int>  $hostIds
     */
    protected function syncHosts(EventType $eventType, array $hostIds): void
    {
        $payload = [];

        foreach (array_values(array_unique($hostIds)) as $position => $hostId) {
            $payload[$hostId] = ['priority' => $position];
        }

        $eventType->hosts()->sync($payload);
    }

    /**
     * Replace the custom questions with the submitted set.
     *
     * @param  array<int, array<string, mixed>>  $questions
     */
    protected function syncQuestions(EventType $eventType, array $questions): void
    {
        $keptIds = [];

        foreach (array_values($questions) as $position => $question) {
            $payload = [
                'type' => $question['type'],
                'label' => $question['label'],
                'help_text' => $question['help_text'] ?? null,
                'options' => $question['options'] ?? null,
                'is_required' => (bool) ($question['is_required'] ?? false),
                'position' => $position,
            ];

            $existing = isset($question['id'])
                ? $eventType->questions()->whereKey($question['id'])->first()
                : null;

            if ($existing !== null) {
                $existing->update($payload);
                $keptIds[] = $existing->id;

                continue;
            }

            $keptIds[] = $eventType->questions()->create($payload)->id;
        }

        $eventType->questions()->whereKeyNot($keptIds)->delete();
    }
}
