<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\LimitPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdvancedAvailabilityRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'limits' => ['array', 'max:3'],
            'limits.*.period' => ['required', Rule::enum(LimitPeriod::class)],
            'limits.*.max_bookings' => ['required', 'integer', 'min:1', 'max:500'],

            'holiday_country' => ['nullable', 'string', 'size:2'],
            'holidays' => ['array'],
            'holidays.*' => ['string', 'max:64'],
        ];
    }

    /**
     * Get the custom validation rules that run after the basic rules pass.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $periods = array_column((array) $this->input('limits', []), 'period');

                if (count($periods) !== count(array_unique($periods))) {
                    $validator->errors()->add('limits', 'Only one limit per period.');
                }
            },
        ];
    }
}
