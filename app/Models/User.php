<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasScheduling;
use App\Concerns\HasTeams;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_super_admin
 * @property string|null $booking_slug
 * @property string $timezone
 * @property string|null $holiday_country
 * @property string|null $welcome_message
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $currentTeam
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read Collection<int, Membership> $teamMemberships
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, AvailabilitySchedule> $availabilitySchedules
 * @property-read Collection<int, EventType> $eventTypes
 * @property-read Collection<int, Booking> $bookings
 * @property-read Collection<int, CalendarAccount> $calendarAccounts
 */
#[Fillable(['name', 'email', 'password', 'is_super_admin', 'current_team_id', 'booking_slug', 'timezone', 'welcome_message', 'holiday_country'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasScheduling, HasTeams, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (User $user) {
            if (empty($user->booking_slug)) {
                $user->booking_slug = static::generateUniqueBookingSlug($user->name);
            }

            if (empty($user->timezone)) {
                $user->timezone = config('scheduling.default_timezone');
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * Determine whether this account can see and act across every organization.
     *
     * A super admin is not a member of every organization — they bypass the
     * membership check instead, so nothing about org rosters changes.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }
}
