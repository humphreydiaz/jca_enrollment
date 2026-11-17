<?php

namespace App\Modules\Enrollment\Http\Requests;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Http\Requests\Concerns\ValidatesIndigenousPeoplesAnswer;
use App\Modules\People\Support\LearnerProfileOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteEnrollmentApplicationRequest extends FormRequest
{
    use ValidatesIndigenousPeoplesAnswer;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'religion' => 'Christianity',
            'guardian_mobile' => filled($this->guardian_mobile) ? $this->guardian_mobile : null,
            'guardian_email' => filled($this->guardian_email) ? $this->guardian_email : null,
            'student_mobile' => filled($this->student_mobile) ? $this->student_mobile : null,
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return $this->indigenousPeoplesAttributes();
    }

    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');
        $user = $this->user();

        return $enrollment instanceof Enrollment
            && $user !== null
            && $user->can('update', $enrollment);
    }

    public static function guardianDetailsPending(Enrollment $enrollment): bool
    {
        return blank($enrollment->guardian_relationship);
    }

    public function rules(): array
    {
        /** @var Enrollment $enrollment */
        $enrollment = $this->route('enrollment');
        $student = $enrollment->student;

        return [
            'lrn' => ['nullable', 'digits:12', Rule::unique('student_profiles', 'lrn')->ignore($student->id)],
            'student_first_name' => ['required', Rule::in([$student->first_name])],
            'student_middle_name' => ['nullable', Rule::in([$student->middle_name])],
            'student_last_name' => ['required', Rule::in([$student->last_name])],
            'extension_name' => ['nullable', Rule::in([$student->extension_name])],
            'sex' => ['required', Rule::in([$student->sex])],
            'birth_date' => ['required', Rule::in([$student->birth_date?->toDateString()])],
            'birth_place' => ['required', 'string', 'max:255'],
            'mother_tongue' => ['required', 'string', 'max:100'],
            'religion' => ['required', Rule::in(['Christianity'])],
            ...$this->indigenousPeoplesRules(),

            'is_4ps_recipient' => ['required', 'boolean'],
            'four_ps_household_id' => [
                Rule::requiredIf($this->boolean('is_4ps_recipient')),
                'nullable',
                'string',
                'max:50',
            ],

            'is_learner_with_disability' => ['required', 'boolean'],
            'disability_type' => [
                Rule::requiredIf($this->boolean('is_learner_with_disability')),
                'nullable',
                Rule::in(array_keys(LearnerProfileOptions::DISABILITY_TYPES)),
            ],

            'current_address' => ['required', 'string', 'max:1000'],
            'same_as_current_address' => ['required', 'boolean'],
            'permanent_address' => [
                Rule::requiredIf(! $this->boolean('same_as_current_address')),
                'nullable',
                'string',
                'max:1000',
            ],

            'father_first_name' => ['nullable', 'string', 'max:100'],
            'father_middle_name' => ['nullable', 'string', 'max:100'],
            'father_last_name' => ['nullable', 'string', 'max:100'],
            'father_extension_name' => ['nullable', 'string', 'max:30'],
            'mother_first_name' => ['nullable', 'string', 'max:100'],
            'mother_middle_name' => ['nullable', 'string', 'max:100'],
            'mother_maiden_last_name' => ['nullable', 'string', 'max:100'],
            'mother_extension_name' => ['nullable', 'string', 'max:30'],

            ...($this->guardianDetailsPending($enrollment) ? [
                'guardian_first_name' => ['required', 'string', 'max:100'],
                'guardian_middle_name' => ['nullable', 'string', 'max:100'],
                'guardian_last_name' => ['required', 'string', 'max:100'],
                'guardian_extension_name' => ['nullable', 'string', 'max:30'],
                'guardian_relationship' => ['required', Rule::in(array_keys(LearnerProfileOptions::GUARDIAN_RELATIONSHIPS))],
            ] : [
                'guardian_first_name' => ['required', Rule::in([$enrollment->guardian->first_name])],
                'guardian_middle_name' => ['nullable', Rule::in([$enrollment->guardian->middle_name])],
                'guardian_last_name' => ['required', Rule::in([$enrollment->guardian->last_name])],
                'guardian_extension_name' => ['nullable', Rule::in([$enrollment->guardian->extension_name])],
                'guardian_relationship' => ['required', Rule::in([$enrollment->guardian_relationship])],
            ]),

            // REQUIRED na ang guardian mobile; optional na ang guardian email
            'guardian_mobile' => [
                'required',
                'string',
                'max:30',
                'regex:/^(?:\+63|0)9\d{9}$/',
            ],

            'guardian_email' => [
                'nullable',
                'email:rfc',
                'max:255',
            ],

            // REQUIRED na ang learner mobile
            'student_mobile' => [
                'required',
                'string',
                'max:30',
                'regex:/^(?:\+63|0)9\d{9}$/',
            ],

            'guardian_sms_opt_in' => ['nullable', 'boolean'],
            'student_sms_opt_in' => ['nullable', 'boolean'],

            'academic_year_id' => ['required', Rule::in([$enrollment->academic_year_id])],
            'grade_level_id' => ['required', Rule::in([$enrollment->grade_level_id])],
            'student_category' => ['required', Rule::in(['regular', 'transferee', 'support_review'])],

            'previous_school_name' => [
                Rule::requiredIf($this->input('student_category') === 'transferee'),
                'nullable',
                'string',
                'max:255',
            ],

            'previous_school_address' => [
                Rule::requiredIf($this->input('student_category') === 'transferee'),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}