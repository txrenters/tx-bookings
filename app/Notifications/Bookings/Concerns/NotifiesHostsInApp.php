<?php

namespace App\Notifications\Bookings\Concerns;

use App\Models\Booking;
use App\Models\User;

/**
 * Adds the in-app notification channel to a booking notification.
 *
 * Only hosts get one. Invitees and guests are reached through
 * Notification::route('mail', ...) as anonymous notifiables — they have no
 * account, so there is nowhere in the app to show them anything.
 */
trait NotifiesHostsInApp
{
    /**
     * Get the delivery channels for the notification.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User
            ? ['mail', 'database']
            : ['mail'];
    }

    /**
     * Build the stored representation shown in the notification panel.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $booking = $this->booking->loadMissing(['eventType', 'team']);
        $timezone = $notifiable instanceof User && filled($notifiable->timezone)
            ? $notifiable->timezone
            : config('scheduling.default_timezone');

        return [
            'type' => $this->inAppType(),
            'title' => $this->inAppTitle(),
            'body' => $this->inAppBody($booking),
            'bookingUid' => $booking->uid,
            'eventTypeName' => $booking->eventType?->name,
            'inviteeName' => $booking->name,
            'startsAt' => $booking->starts_at->toIso8601String(),
            'whenLabel' => $booking->starts_at->setTimezone($timezone)->isoFormat('ddd D MMM, h:mm a'),
            'teamSlug' => $booking->team?->slug,
        ];
    }

    /**
     * Get the machine-readable kind of this notification.
     */
    abstract protected function inAppType(): string;

    /**
     * Get the short heading shown in the panel.
     */
    abstract protected function inAppTitle(): string;

    /**
     * Get the one-line summary shown under the heading.
     */
    abstract protected function inAppBody(Booking $booking): string;
}
