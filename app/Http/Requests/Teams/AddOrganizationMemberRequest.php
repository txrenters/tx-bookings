<?php

namespace App\Http\Requests\Teams;

use App\Concerns\ProfileValidationRules;
use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddOrganizationMemberRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * The address is deliberately NOT unique here, unlike creating an account:
     * an existing one means "add this person", and only a new address needs a
     * name to create an account with.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            // A name is only needed when there is no account yet: an address
            // that already has one is being added, not created.
            'name' => [
                Rule::requiredIf(fn (): bool => ! User::query()
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $this->input('email'))])
                    ->exists()),
                'nullable',
                'string',
                'max:255',
            ],
            'role' => ['required', 'string', Rule::enum(TeamRole::class)],
        ];
    }
}
