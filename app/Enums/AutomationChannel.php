<?php

namespace App\Enums;

enum AutomationChannel: string
{
    case Email = 'email';
    case Sms = 'sms';

    /**
     * Get the display label for the channel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Email => 'Send email',
            self::Sms => 'Send text message',
        };
    }

    /**
     * Get a compact label, for the listing's "Do this" column.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Sms => 'Text',
        };
    }

    /**
     * Get the options offered in the editor.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $channel) => ['value' => $channel->value, 'label' => $channel->label()],
            self::cases(),
        );
    }
}
