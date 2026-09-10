<?php

namespace App\Services\Sms;

use App\Support\PhoneNumber;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to Twilio's REST API over plain HTTP, the way the calendar and mail
 * providers do -- no SDK, so tests fake it with Http::fake().
 *
 * The account is the installation's; each organization picks which of its
 * numbers to send from (Team::sms_from_number).
 */
class TwilioClient
{
    protected const NUMBERS_CACHE_KEY = 'twilio.numbers';

    /**
     * Determine whether the installation has Twilio credentials at all.
     *
     * Blank keys are the normal state before anyone has signed up for Twilio,
     * so nothing may assume this is configured. The env file ships the keys
     * present but empty, which is why this checks blank() rather than trusting
     * the config to be missing.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.twilio.sid')) && filled(config('services.twilio.token'));
    }

    /**
     * Get the numbers the account can send from.
     *
     * Cached briefly: this is read every time an admin opens the organization
     * settings, and the list changes only when someone buys a number.
     *
     * @return array<int, array{number: string, label: string}>
     */
    public function numbers(bool $fresh = false): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        if ($fresh) {
            Cache::forget(self::NUMBERS_CACHE_KEY);
        }

        return Cache::remember(self::NUMBERS_CACHE_KEY, now()->addMinutes(10), function () {
            $response = $this->request()->get($this->url('IncomingPhoneNumbers.json'), ['PageSize' => 100]);

            if ($response->failed()) {
                return [];
            }

            $payload = $response->json('incoming_phone_numbers');

            if (! is_array($payload)) {
                return [];
            }

            $numbers = [];

            foreach ($payload as $entry) {
                // A number is only useful if Twilio told us what it is; a
                // friendly name is a convenience and falls back to the number.
                $number = is_array($entry) ? (string) ($entry['phone_number'] ?? '') : '';

                if ($number === '') {
                    continue;
                }

                $numbers[] = [
                    'number' => $number,
                    'label' => (string) ($entry['friendly_name'] ?? $number),
                ];
            }

            return $numbers;
        });
    }

    /**
     * Send one text message.
     *
     * @throws RuntimeException when Twilio refuses it
     */
    public function send(string $from, string $to, string $body): void
    {
        $recipient = PhoneNumber::toE164($to);

        if ($recipient === null) {
            throw new RuntimeException('"'.$to.'" is not a number that can be texted.');
        }

        if (! $this->isConfigured()) {
            throw new RuntimeException('Twilio is not configured.');
        }

        $response = $this->request()->asForm()->post($this->url('Messages.json'), [
            'From' => $from,
            'To' => $recipient,
            'Body' => $body,
        ]);

        if ($response->failed()) {
            // Twilio explains itself in the body; the status alone says little.
            throw new RuntimeException(
                'Twilio refused the message: '.($response->json('message') ?? $response->status()),
            );
        }
    }

    /**
     * Start a request carrying the account's credentials.
     */
    protected function request(): PendingRequest
    {
        return Http::withBasicAuth(
            (string) config('services.twilio.sid'),
            (string) config('services.twilio.token'),
        )->timeout(15);
    }

    /**
     * Build the URL of an account-scoped resource.
     */
    protected function url(string $resource): string
    {
        return rtrim((string) config('services.twilio.base_url'), '/')
            .'/Accounts/'.config('services.twilio.sid').'/'.$resource;
    }
}
