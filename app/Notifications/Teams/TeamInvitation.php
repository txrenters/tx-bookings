<?php

namespace App\Notifications\Teams;

use App\Models\TeamInvitation as TeamInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public TeamInvitationModel $invitation)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $team = $this->invitation->team;

        /*
         * The invitation deliberately does not name whoever sent it. Who
         * issued an invitation is an internal detail of the organization, and
         * the recipient only needs to know which organization is asking.
         *
         * One button for everyone: the join route decides what the click means.
         * An address with no account yet is created and signed in on the spot;
         * one that already has an account is sent to sign in, since a link from
         * an inbox must not walk past that account's password and second
         * factor.
         */
        return (new MailMessage)
            ->subject(__("You've been invited to join :teamName", ['teamName' => $team->name]))
            ->line(__('You have been invited to join the :teamName organization.', [
                'teamName' => $team->name,
            ]))
            ->action(
                __('Join Now'),
                route('invitations.join', ['invitation' => $this->invitation->code]),
            )
            ->line(__('This invitation expires in three days and can only be used once.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'team_id' => $this->invitation->team_id,
            'team_name' => $this->invitation->team->name,
            'role' => $this->invitation->role->value,
        ];
    }
}
