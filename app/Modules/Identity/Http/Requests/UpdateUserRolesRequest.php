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

    /**
     * @return array<string, list<\Illuminate\Contracts\Validation\Rule|string>>
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(array_map(static fn (RoleCode $role): string => $role->value, RoleCode::cases()))],
        ];
    }
}
