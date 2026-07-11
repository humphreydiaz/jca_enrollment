<?php

namespace App\Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnrollmentDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resume_token' => ['required', 'string', 'size:64'],
            'guardian_relationship' => ['required', 'string', 'max:50'],
            'student_middle_name' => ['nullable', 'string', 'max:100'],
            'previous_school_name' => [Rule::requiredIf($this->route('enrollment')?->student_category === 'transferee'), 'nullable', 'string', 'max:255'],
        ];
    }
}
