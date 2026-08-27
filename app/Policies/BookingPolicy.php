<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Booking $booking): bool
    {
        if ($booking->user_id === $user->id || $booking->hosts->contains('id', $user->id)) {
            return true;
        }

        return $user->hasTeamPermission($booking->team, TeamPermission::ManageTeamBookings);
    }

    /**
     * Determine whether the user can cancel the model.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return $this->view($user, $booking);
    }
}
