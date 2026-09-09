<?php

namespace App\Enums;

enum QuestionType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Select = 'select';
    case MultiSelect = 'multi_select';
    case YesNo = 'yes_no';
    case Phone = 'phone';

    /**
     * Get the display label for the question type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Text => 'Single line',
            self::Textarea => 'Multiple lines',
            self::Select => 'Pick one',
            self::MultiSelect => 'Pick multiple',
            self::YesNo => 'Yes or no',
            self::Phone => 'Phone number',
        };
    }

    /**
     * Determine if the question's answers are written by whoever asks it.
     *
     * Yes or no has answers too, but they are not the organizer's to choose,
     * so the editor offers no list to fill in.
     */
    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::MultiSelect], true);
    }

    /**
     * Get the answers the question accepts, or an empty list when they are
     * whatever the organizer typed.
     *
     * @param  array<int, string>  $configured
     * @return array<int, string>
     */
    public function answerOptions(array $configured = []): array
    {
        return match ($this) {
            self::YesNo => ['Yes', 'No'],
            self::Select, self::MultiSelect => array_values($configured),
            default => [],
        };
    }

    /**
     * Determine if the question accepts more than one answer.
     */
    public function isMultiValue(): bool
    {
        return $this === self::MultiSelect;
    }

    /**
     * Determine if an answer has to be one of a fixed set.
     */
    public function isConstrained(): bool
    {
        return $this->hasOptions() || $this === self::YesNo;
    }
}
