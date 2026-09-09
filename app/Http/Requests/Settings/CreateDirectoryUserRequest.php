<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Enums\TeamRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDirectoryUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * A super admin belongs to no organization, so the organization and role
     * are required only for everyone else.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'is_super_admin' => ['boolean'],
            'team_id' => [
                Rule::requiredIf(fn (): bool => ! $this->boolean('is_super_admin')),
                'nullable',
                'integer',
                'exists:teams,id',
            ],
            'role' => [
                Rule::requiredIf(fn (): bool => ! $this->boolean('is_super_admin')),
                'nullable',
                Rule::enum(TeamRole::class),
            ],
        ];
    }
}
