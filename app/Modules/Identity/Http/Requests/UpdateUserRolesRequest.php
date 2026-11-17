<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\RoleCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'roles' => [
                'required',
                'array',
                'size:1',
            ],
            'roles.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(
                    array_map(
                        static fn (RoleCode $role): string => $role->value,
                        RoleCode::cases()
                    )
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'roles.required' => 'Please select a user role.',
            'roles.size' => 'Only one role can be assigned to each account.',
            'roles.*.in' => 'The selected role is invalid.',
        ];
    }
}