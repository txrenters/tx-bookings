<?php

namespace App\Http\Requests\Scheduling;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAvailabilityScheduleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'is_default' => ['boolean'],
            'is_shared' => ['boolean'],
            'is_active' => ['boolean'],

            'rules' => ['array', 'max:70'],
            'rules.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'rules.*.starts_at' => ['required', 'date_format:H:i'],
            'rules.*.ends_at' => ['required', 'date_format:H:i', 'after:rules.*.starts_at'],

            'overrides' => ['array', 'max:200'],
            'overrides.*.date' => ['required', 'date_format:Y-m-d'],
            'overrides.*.is_unavailable' => ['boolean'],
            'overrides.*.starts_at' => ['nullable', 'date_format:H:i', 'required_if:overrides.*.is_unavailable,false'],
            'overrides.*.ends_at' => ['nullable', 'date_format:H:i', 'after:overrides.*.starts_at'],
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
                foreach ($this->input('rules', []) as $index => $rule) {
                    foreach ($this->input('rules', []) as $otherIndex => $other) {
                        if ($index >= $otherIndex || $rule['day_of_week'] !== $other['day_of_week']) {
                            continue;
                        }

                        if ($rule['starts_at'] < $other['ends_at'] && $other['starts_at'] < $rule['ends_at']) {
                            $validator->errors()->add("rules.{$index}.starts_at", 'These hours overlap another block on the same day.');
                        }
                    }
                }
            },
        ];
    }
}
