<?php

namespace App\Enums;

enum AutomationTrigger: string
{
    case Booked = 'booked';
    case BeforeStart = 'before_start';
    case AfterEnd = 'after_end';

    /**
     * Get the display label for the trigger.
     */
    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Immediately when a meeting is booked',
            self::BeforeStart => 'Before the meeting starts',
            self::AfterEnd => 'After the meeting ends',
        };
    }

    /**
     * Determine whether an offset has to be given.
     *
     * Booking is a moment, so a delay after it is optional -- "immediately"
     * is a perfectly good answer. The meeting's own times are only useful
     * with a distance from them.
     */
    public function requiresOffset(): bool
    {
        return $this !== self::Booked;
    }

    /**
     * Get the options offered in the editor.
     *
     * @return array<int, array{value: string, label: string, requiresOffset: bool}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $trigger) => [
                'value' => $trigger->value,
                'label' => $trigger->label(),
                'requiresOffset' => $trigger->requiresOffset(),
            ],
            self::cases(),
        );
    }
}
