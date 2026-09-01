<?php

namespace App\Notifications\Bookings;

use App\Models\Booking;
use App\Notifications\Bookings\Concerns\NotifiesHostsInApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingPendingApproval extends Notification implements ShouldQueue
{
    use NotifiesHostsInApp, Queueable;

    public function __construct(public Booking $booking)
    {
        //
    }

    /**
     * Build the mail representation of the notification.
     *
     * No calendar invite is attached: nothing is on anyone's calendar until a
     * host approves, at which point the standard confirmation carries the ICS.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['eventType', 'host', 'guests', 'answers']);
        $presenter = new BookingMailPresenter($booking, $notifiable);

        $subject = $presenter->isHost()
            ? 'Approval needed: '.$booking->eventType->name.' on '.$presenter->when()
            : 'Request received: '.$booking->eventType->name.' on '.$presenter->when();

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Hi '.$presenter->recipientName().',')
            ->line($presenter->isHost()
                ? $booking->name.' requested '.$booking->eventType->name.' with you.'
                : 'Your request for '.$booking->eventType->name.' has been sent.')
            ->line('**When:** '.$presenter->when())
            ->line('**Who:** '.$presenter->participants());

        if (! $presenter->isHost()) {
            $message->line("You'll get a confirmation email once a host approves it.");
        }

        return $presenter->withFooter($message);
    }

    /**
     * Get the machine-readable kind of this notification.
     */
    protected function inAppType(): string
    {
        return 'booking.pending';
    }

    /**
     * Get the short heading shown in the panel.
     */
    protected function inAppTitle(): string
    {
        return 'Approval needed';
    }

    /**
     * Get the one-line summary shown under the heading.
     */
    protected function inAppBody(Booking $booking): string
    {
        return $booking->name.' requested '.($booking->eventType?->name ?? 'a meeting').'.';
    }
}
