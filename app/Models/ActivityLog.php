<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One recorded thing that happened inside an organization.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property string $event
 * @property string $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $properties
 * @property string|null $actor_name
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property-read Team $team
 * @property-read User|null $user
 */
#[Fillable([
    'team_id', 'user_id', 'event', 'description', 'subject_type', 'subject_id',
    'properties', 'actor_name', 'ip_address',
])]
class ActivityLog extends Model
{
    /**
     * Get the organization the activity belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the person who did it, if anyone was signed in.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the record the activity was about.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to a single event family, for example "event_type".
     *
     * @param  Builder<ActivityLog>  $query
     */
    public function scopeOfKind(Builder $query, string $kind): void
    {
        $query->where('event', 'like', $kind.'.%');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }
}
