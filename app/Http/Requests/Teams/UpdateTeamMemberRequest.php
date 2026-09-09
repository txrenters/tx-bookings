<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Owner is accepted here, unlike on an invitation: naming a new
            // owner is a transfer, and the controller demotes the old one in
            // the same transaction. Only the owner and super admins reach this
            // endpoint at all -- UpdateMember is an owner-only permission.
            'role' => ['required', 'string', Rule::enum(TeamRole::class)],
        ];
    }
}
