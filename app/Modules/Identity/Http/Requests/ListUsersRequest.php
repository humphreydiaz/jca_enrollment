<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Enums\RoleCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(RoleCode::Admin->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => filled($this->input('search')) ? trim((string) $this->input('search')) : null,
            'role' => filled($this->input('role')) ? $this->input('role') : null,
            'status' => filled($this->input('status')) ? $this->input('status') : null,
            'sort' => filled($this->input('sort')) ? $this->input('sort') : null,
            'per_page' => filled($this->input('per_page')) ? $this->input('per_page') : null,
        ]);
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::enum(RoleCode::class)],
            'status' => ['nullable', Rule::enum(AccountStatus::class)],
            'sort' => ['nullable', Rule::in(['name_asc', 'name_desc', 'newest', 'oldest', 'recent_login'])],
            'per_page' => ['nullable', 'integer', Rule::in([15, 30, 50])],
        ];
    }
}
