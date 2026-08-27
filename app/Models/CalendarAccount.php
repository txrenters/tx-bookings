<?php

namespace App\Models;

use App\Enums\CalendarProvider;
use Database\Factories\CalendarAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property CalendarProvider $provider
 * @property string $external_id
 * @property string $email
 * @property string $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $last_synced_at
 * @property string|null $sync_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Calendar> $calendars
 */
#[Fillable([
    'user_id', 'provider', 'external_id', 'email', 'access_token', 'refresh_token',
    'token_expires_at', 'last_synced_at', 'sync_error',
])]
#[Hidden(['access_token', 'refresh_token'])]
class CalendarAccount extends Model
{
    /** @use HasFactory<CalendarAccountFactory> */
    use HasFactory;

    /**
     * Get the user that connected the account.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the calendars discovered on the account.
     *
     * @return HasMany<Calendar, $this>
     */
    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class);
    }

    /**
     * Get the calendar that bookings should be written to.
     */
    public function writeTarget(): ?Calendar
    {
        return $this->calendars()->where('is_write_target', true)->first()
            ?? $this->calendars()->where('is_primary', true)->first();
    }

    /**
     * Determine if the stored access token has expired.
     */
    public function tokenHasExpired(): bool
    {
        return $this->token_expires_at !== null
            && $this->token_expires_at->subSeconds(60)->isPast();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => CalendarProvider::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
