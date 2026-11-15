<?php

namespace App\Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', \App\Modules\Enrollment\Models\Enrollment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['submitted', 'under_review', 'incomplete', 'assessment_required', 'assessment_pending', 'approved', 'waitlisted', 'rejected', 'withdrawn', 'cancelled'])],
            'reason' => [Rule::requiredIf(in_array($this->input('status'), ['incomplete', 'waitlisted', 'rejected', 'withdrawn', 'cancelled'], true)), 'nullable', 'string', 'max:2000'],
        ];
    }
}
