<?php

namespace App\Services\Activity;

use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Throwable;

/**
 * Writes the organization audit trail.
 *
 * Recording is best effort: an audit row must never be the reason a booking or
 * a settings change fails, so a write that blows up is swallowed and reported
 * to the log rather than bubbling into the request.
 */
class ActivityLogger
{
    /**
     * Record one thing that happened.
     *
     * @param  array<string, mixed>  $properties
     */
    public function record(
        Team $team,
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $actor = null,
    ): ?ActivityLog {
        try {
            $actor ??= Auth::user();

            return ActivityLog::create([
                'team_id' => $team->id,
                'user_id' => $actor?->id,
                'event' => $event,
                'description' => $description,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'properties' => $properties === [] ? null : $properties,
                // Kept separately so the trail still reads correctly after the
                // account is renamed or deleted.
                'actor_name' => $actor?->name,
                'ip_address' => Request::ip(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
