<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),

            /*
             * SVG is deliberately excluded, as it is for organization logos:
             * photos are served from the app's own origin, and an SVG can
             * carry script.
             */
            'photo' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:2048',
                'dimensions:max_width=2000,max_height=2000',
            ],
            'remove_photo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.mimes' => __('The photo must be a PNG, JPG or WebP image.'),
            'photo.max' => __('The photo must be 2 MB or smaller.'),
            'photo.dimensions' => __('The photo must be no larger than 2000 x 2000 pixels.'),
        ];
    }
}
