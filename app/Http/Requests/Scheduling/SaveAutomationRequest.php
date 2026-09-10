<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\AutomationChannel;
use App\Enums\AutomationRecipient;
use App\Enums\AutomationTrigger;
use App\Models\Automation;
use App\Rules\DialableNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAutomationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $automation = $this->route('automation');
        $teamId = $automation instanceof Automation ? $automation->team_id : $this->user()->current_team_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', Rule::enum(AutomationTrigger::class)],
            /*
             * A delay is what makes the timed triggers mean anything, so it is
             * required there and capped at a year. "When it is booked" happens
             * at a moment of its own, and an offset only postpones it.
             */
            'offset_minutes' => [
                'nullable',
                'integer',
                'min:0',
                'max:525600',
                Rule::requiredIf(fn () => AutomationTrigger::tryFrom((string) $this->input('trigger'))?->requiresOffset() === true),
            ],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::enum(AutomationChannel::class)],
            'recipient' => ['required', Rule::enum(AutomationRecipient::class)],
            'recipient_emails' => [
                'array',
                'max:20',
                Rule::requiredIf(fn () => $this->sendsTo(AutomationRecipient::Someone) && $this->uses(AutomationChannel::Email)),
            ],
            'recipient_emails.*' => ['email', 'max:255'],
            'recipient_phones' => [
                'array',
                'max:20',
                Rule::requiredIf(fn () => $this->sendsTo(AutomationRecipient::Someone) && $this->uses(AutomationChannel::Sms)),
            ],
            'recipient_phones.*' => ['string', 'max:20', new DialableNumber],
            'subject' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $this->uses(AutomationChannel::Email))],
            'body' => ['nullable', 'string', 'max:5000', Rule::requiredIf(fn () => $this->uses(AutomationChannel::Email))],
            /*
             * Twilio bills per 160 character segment, so a workflow that runs
             * away with the wording gets expensive quietly. Four segments is
             * plenty for "your meeting moved" and is what Calendly allows.
             */
            'sms_body' => ['nullable', 'string', 'max:640', Rule::requiredIf(fn () => $this->uses(AutomationChannel::Sms))],
            'is_active' => ['boolean'],
            // None means every event type in the organization, now and later.
            'event_type_ids' => ['array'],
            'event_type_ids.*' => [
                'integer',
                Rule::exists('event_types', 'id')->where('team_id', $teamId)->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'offset_minutes.required' => 'Say how long before or after the meeting this should be sent.',
            'channels.required' => 'Choose whether this sends an email, a text message, or both.',
            'recipient_emails.required' => 'Give at least one address this should be emailed to.',
            'recipient_phones.required' => 'Give at least one number this should be texted to.',
            'subject.required' => 'Give the email a subject.',
            'body.required' => 'Write the email.',
            'sms_body.required' => 'Write the text message.',
        ];
    }

    /**
     * Determine whether the submitted workflow uses the given channel.
     */
    protected function uses(AutomationChannel $channel): bool
    {
        return in_array($channel->value, (array) $this->input('channels', []), true);
    }

    /**
     * Determine whether the submitted workflow sends to the given recipient.
     */
    protected function sendsTo(AutomationRecipient $recipient): bool
    {
        return $this->input('recipient') === $recipient->value;
    }
}
