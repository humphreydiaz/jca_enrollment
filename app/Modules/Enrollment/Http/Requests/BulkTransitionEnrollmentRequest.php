<?php

namespace App\Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTransitionEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'registrar']) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'enrollment_public_ids' => ['required', 'array', 'min:1', 'max:100'],
            'enrollment_public_ids.*' => ['required', 'ulid', 'exists:enrollments,public_id'],
            'status' => ['required', Rule::in(['submitted', 'under_review', 'incomplete', 'assessment_required', 'assessment_pending', 'approved', 'waitlisted', 'rejected', 'withdrawn', 'cancelled'])],
            'reason' => [Rule::requiredIf(in_array($this->input('status'), ['incomplete', 'waitlisted', 'rejected', 'withdrawn', 'cancelled'], true)), 'nullable', 'string', 'max:2000'],
        ];
    }
}
