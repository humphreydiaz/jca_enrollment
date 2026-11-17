<?php

namespace App\Modules\People\Http\Requests;

use App\Modules\People\Support\LearnerProfileOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConnectSiblingGuardianRequest extends FormRequest
{
    /** Errors show inside the matching learner's card. */
    protected function prepareForValidation(): void
    {
        $student = $this->route('student');
        $this->errorBag = 'sibling-'.(is_object($student) ? $student->public_id : (string) $student);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'registrar']) ?? false;
    }

    public function rules(): array
    {
        return [
            'sibling_student_number' => ['required', 'string', 'max:30'],
            'guardian' => ['nullable', 'ulid'],
            'relationship_type' => ['required', Rule::in(array_keys(LearnerProfileOptions::GUARDIAN_RELATIONSHIPS))],
            'confirmed_in_person' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'sibling_student_number.required' => 'Enter the Student ID of the other child the parent already signs in for.',
            'confirmed_in_person.accepted' => 'Confirm that you checked the parent’s identity in person.',
        ];
    }
}
