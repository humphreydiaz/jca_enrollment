<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\RoleCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::in(['email', 'mobile'])],
            'email' => ['nullable', 'email', 'max:255', 'required_if:channel,email'],
            'mobile' => ['nullable', 'string', 'max:30', 'required_if:channel,mobile'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(array_map(static fn (RoleCode $role): string => $role->value, RoleCode::cases()))],
        ];
    }
}
