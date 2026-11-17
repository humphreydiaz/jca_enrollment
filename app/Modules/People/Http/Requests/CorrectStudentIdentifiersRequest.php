<?php

namespace App\Modules\People\Http\Requests;

use App\Modules\People\Models\StudentProfile;
use App\Modules\People\Support\StudentNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrectStudentIdentifiersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', StudentProfile::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('student_number')) {
            $this->merge(['student_number' => StudentNumber::normalize($this->string('student_number')->toString())]);
        }
    }

    public function rules(): array
    {
        /** @var StudentProfile $student */
        $student = $this->route('student');

        return [
            'student_number' => ['required', 'regex:'.StudentNumber::PATTERN, Rule::unique('student_profiles', 'student_number')->ignore($student->id)],
            'lrn' => ['nullable', 'digits:12', Rule::unique('student_profiles', 'lrn')->ignore($student->id)],
            'reason' => ['required', 'string', 'min:10', 'max:2000', 'not_regex:/^(?:n\/?a|none|test|unknown)$/i'],
        ];
    }
}
