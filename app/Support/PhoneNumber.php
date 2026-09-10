<?php

namespace App\Support;

/**
 * Puts the phone numbers this app collects into the shape Twilio insists on.
 */
class PhoneNumber
{
    /**
     * Get the number in E.164 form, or null when it cannot be one.
     *
     * Numbers reach us typed by hand -- an invitee answering a booking
     * question, a host filling in their profile -- so "(512) 555-0100" and
     * "512.555.0100" both have to become +15125550100. A bare ten digit
     * number is assumed to be American, which every number in this system is;
     * anything international has to be written with its + and country code.
     */
    public static function toE164(?string $number): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $number) ?? '';

        if ($digits === '') {
            return null;
        }

        // Already international, and written as such.
        if (str_starts_with(trim((string) $number), '+')) {
            return '+'.$digits;
        }

        return match (true) {
            strlen($digits) === 10 => '+1'.$digits,
            strlen($digits) === 11 && str_starts_with($digits, '1') => '+'.$digits,
            default => null,
        };
    }

    /**
     * Determine whether a number can be texted.
     */
    public static function isValid(?string $number): bool
    {
        return self::toE164($number) !== null;
    }
}
