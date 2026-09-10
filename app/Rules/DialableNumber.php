<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Accepts a phone number only if it can be put into the E.164 form Twilio
 * dials -- ten digits for an American number, or written with its country
 * code. Punctuation is the writer's business; the app normalises it on save.
 */
class DialableNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! PhoneNumber::isValid(is_string($value) ? $value : null)) {
            $fail(__('Enter a phone number as ten digits, or with its country code.'));
        }
    }
}
