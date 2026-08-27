<?php

namespace App\Enums;

enum QuestionType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Select = 'select';
    case MultiSelect = 'multi_select';
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
            self::Phone => 'Phone number',
        };
    }

    /**
     * Determine if the question offers a fixed set of answers.
     */
    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::MultiSelect], true);
    }

    /**
     * Determine if the question accepts more than one answer.
     */
    public function isMultiValue(): bool
    {
        return $this === self::MultiSelect;
    }
}
