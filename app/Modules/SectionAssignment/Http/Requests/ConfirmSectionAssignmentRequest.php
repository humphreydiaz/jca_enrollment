<?php

namespace App\Modules\SectionAssignment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmSectionAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->hasAnyRole(['admin', 'registrar']) === true
            && (! $this->boolean('override_capacity') || $user->hasRole('admin'));
    }

    public function rules(): array
    {
        return [
            'section_public_id' => ['required', 'ulid', Rule::exists('sections', 'public_id')],
            'support_suitability_confirmed' => ['nullable', 'boolean'],
            'override_capacity' => ['nullable', 'boolean'],
            'override_reason' => [Rule::requiredIf($this->boolean('override_capacity')), 'nullable', 'string', 'max:2000'],
        ];
    }
}
