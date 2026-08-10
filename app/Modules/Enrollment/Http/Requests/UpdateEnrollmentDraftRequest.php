<?php

namespace App\Modules\Enrollment\Http\Requests;

use App\Modules\People\Support\LearnerProfileOptions;
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
        $enrollment = $this->route('enrollment');
        $isStudentOwner = $enrollment?->student_applicant_user_id !== null
            && $this->user()?->id === $enrollment->student_applicant_user_id
            && $this->user()->hasRole('student');

        return [
            'resume_token' => [Rule::requiredIf(! $isStudentOwner), 'nullable', 'string', 'size:64'],
            'guardian_relationship' => ['required', Rule::in(array_keys(LearnerProfileOptions::GUARDIAN_RELATIONSHIPS))],
            'student_middle_name' => ['nullable', 'string', 'max:100'],
            'previous_school_name' => [Rule::requiredIf($this->route('enrollment')?->student_category === 'transferee'), 'nullable', 'string', 'max:255'],
            'previous_school_address' => [Rule::requiredIf($this->route('enrollment')?->student_category === 'transferee'), 'nullable', 'string', 'max:255'],
        ];
    }
}
