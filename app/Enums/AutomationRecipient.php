<?php

namespace App\Enums;

enum AutomationRecipient: string
{
    case Host = 'host';
    case Invitee = 'invitee';
    case Someone = 'someone';

    /**
     * Get the display label for the recipient.
     */
    public function label(): string
    {
        return match ($this) {
            self::Host => 'The meeting\'s hosts',
            self::Invitee => 'The invitee',
            self::Someone => 'Someone else',
        };
    }

    /**
     * Determine whether an address has to be given, rather than worked out
     * from the booking.
     */
    public function needsAddress(): bool
    {
        return $this === self::Someone;
    }

    /**
     * Get the options offered in the editor.
     *
     * @return array<int, array{value: string, label: string, needsAddress: bool}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $recipient) => [
                'value' => $recipient->value,
                'label' => $recipient->label(),
                'needsAddress' => $recipient->needsAddress(),
            ],
            self::cases(),
        );
    }
}
