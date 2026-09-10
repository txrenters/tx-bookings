<?php

namespace App\Http\Requests\Teams;

use App\Rules\TeamName;
use App\Services\Sms\TwilioClient;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTeamRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Everything past the name is organization branding, which only the update
     * path sends — creation just names the organization.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new TeamName],
            'welcome_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'website_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'timezone' => ['sometimes', 'nullable', 'timezone'],
            /*
             * Only a number the Twilio account actually owns: the list is what
             * the picker offers, and a number typed anywhere else would fail at
             * send time rather than here. When the account cannot be reached
             * the only allowed answer is "none", so a hiccup cannot silently
             * point an organization at a number nobody has checked.
             */
            'sms_from_number' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(array_column(app(TwilioClient::class)->numbers(), 'number')),
            ],
            /**
             * SVG is deliberately excluded: logos are served from the app's own
             * origin, and an SVG can carry script.
             */
            'logo' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:2048',
                Rule::dimensions()->maxWidth(2000)->maxHeight(2000),
            ],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.mimes' => __('The logo must be a PNG, JPG or WebP image.'),
            'logo.max' => __('The logo must be 2 MB or smaller.'),
            'logo.dimensions' => __('The logo must be no larger than 2000 x 2000 pixels.'),
        ];
    }
}
