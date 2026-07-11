<?php

namespace App\Modules\People\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkGuardianStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'guardian_public_id' => ['required', 'ulid', 'exists:guardian_profiles,public_id'],
            'student_public_id' => ['required', 'ulid', 'exists:student_profiles,public_id'],
            'relationship_type' => ['required', 'string', 'max:50'],
            'is_primary_contact' => ['nullable', 'boolean'],
            'may_receive_sms' => ['nullable', 'boolean'],
            'may_view_financials' => ['nullable', 'boolean'],
            'may_manage_enrollment' => ['nullable', 'boolean'],
            'has_legal_authority' => ['nullable', 'boolean'],
        ];
    }
}
