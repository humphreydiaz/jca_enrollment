<?php

namespace App\Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewEnrollmentDuplicateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('enrollment')) ?? false;
    }

    public function rules(): array
    {
        return [
            'duplicate_decision' => ['required', Rule::in(['clear', 'link_existing'])],
            'student_public_id' => ['nullable', 'ulid', 'exists:student_profiles,public_id'],
            'guardian_public_id' => ['nullable', 'ulid', 'exists:guardian_profiles,public_id'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
