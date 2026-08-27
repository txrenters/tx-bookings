<?php

namespace App\Notifications\Bookings;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Formats booking details for whichever party is being emailed, so times
 * always land in the reader's own timezone.
 */
class BookingMailPresenter
{
    public function __construct(
        protected Booking $booking,
        protected object $notifiable,
    ) {
        //
    }

    /**
     * Get the recipient as a host, or null when the invitee is being emailed.
     */
    public function hostRecipient(): ?User
    {
        return $this->notifiable instanceof User ? $this->notifiable : null;
    }

    /**
     * Determine if the email is going to one of the hosts.
     */
    public function isHost(): bool
    {
        return $this->hostRecipient() !== null;
    }

    /**
     * Get the name to greet the recipient by.
     */
    public function recipientName(): string
    {
        $host = $this->hostRecipient();
        $name = $host !== null ? $host->name : $this->booking->name;

        return str($name)->before(' ')->toString();
    }

    /**
     * Get the timezone the recipient reads times in.
     */
    public function timezone(): string
    {
        $host = $this->hostRecipient();
        $timezone = $host !== null ? $host->timezone : $this->booking->invitee_timezone;

        return $timezone ?: config('scheduling.default_timezone');
    }

    /**
     * Get the meeting time formatted for the recipient.
     */
    public function when(): string
    {
        $timezone = $this->timezone();

        return $this->booking->starts_at->setTimezone($timezone)->format('l, j F Y \a\t g:ia')
            .' - '.$this->booking->ends_at->setTimezone($timezone)->format('g:ia')
            .' ('.$timezone.')';
    }

    /**
     * Get the line summarising who is meeting whom.
     */
    public function participants(): string
    {
        $hosts = $this->booking->hosts->isNotEmpty()
            ? $this->booking->hosts->pluck('name')
            : collect([$this->booking->host->name]);

        return $hosts->implode(', ').' and '.$this->booking->name;
    }

    /**
     * Get the opening line for a confirmation email.
     */
    public function confirmationLine(): string
    {
        return $this->isHost()
            ? $this->booking->name.' booked '.$this->booking->eventType->name.' with you.'
            : 'Your '.$this->booking->eventType->name.' is confirmed.';
    }

    /**
     * Get the joining details for the meeting.
     */
    public function location(): ?string
    {
        return $this->booking->meeting_url ?: $this->booking->location_detail;
    }

    /**
     * Add the manage links appropriate to the recipient.
     */
    public function withFooter(MailMessage $message): MailMessage
    {
        if ($this->isHost()) {
            return $message->action('View booking', route('meetings.index', ['current_team' => $this->booking->team->slug]));
        }

        return $message
            ->action('Reschedule', route('booking.reschedule', ['booking' => $this->booking->uid]))
            ->line('Need to cancel? [Cancel this meeting]('.route('booking.cancel', ['booking' => $this->booking->uid]).')');
    }
}
