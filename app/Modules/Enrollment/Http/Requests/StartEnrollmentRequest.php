<?php

namespace App\Modules\Enrollment\Http\Requests;

use App\Modules\Academics\Models\GradeLevel;
use App\Modules\People\Support\LearnerProfileOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $studentSelfApplicant = $this->user()?->hasRole('student') ?? false;

        return [
            'lrn' => ['nullable', 'digits:12'],
            'student_first_name' => ['required', 'string', 'max:100'],
            'student_middle_name' => ['nullable', 'string', 'max:100'],
            'student_last_name' => ['required', 'string', 'max:100'],
            'extension_name' => ['nullable', 'string', 'max:30'],
            'sex' => ['required', Rule::in(array_keys(LearnerProfileOptions::SEXES))],
            'birth_date' => ['required', 'date', 'before:today'],
            'birth_place' => ['required', 'string', 'max:255'],
            'mother_tongue' => ['required', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'ip_group' => ['nullable', 'string', 'max:150'],
            'is_4ps_recipient' => ['required', 'boolean'],
            'four_ps_household_id' => [Rule::requiredIf($this->boolean('is_4ps_recipient')), 'nullable', 'string', 'max:50'],
            'is_learner_with_disability' => ['required', 'boolean'],
            'disability_type' => [Rule::requiredIf($this->boolean('is_learner_with_disability')), 'nullable', Rule::in(array_keys(LearnerProfileOptions::DISABILITY_TYPES))],
            'current_address' => ['required', 'string', 'max:1000'],
            'same_as_current_address' => ['required', 'boolean'],
            'permanent_address' => [Rule::requiredIf(! $this->boolean('same_as_current_address')), 'nullable', 'string', 'max:1000'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mother_maiden_name' => ['nullable', 'string', 'max:255'],
            'guardian_first_name' => ['required', 'string', 'max:100'],
            'guardian_middle_name' => ['nullable', 'string', 'max:100'],
            'guardian_last_name' => ['required', 'string', 'max:100'],
            'guardian_relationship' => ['required', Rule::in(array_keys(LearnerProfileOptions::GUARDIAN_RELATIONSHIPS))],
            'guardian_mobile' => ['required', 'string', 'max:30', 'regex:/^(?:\+63|0)9\d{9}$/'],
            'guardian_email' => ['nullable', 'email:rfc', 'max:255'],
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')->whereIn('status', ['planned', 'open'])],
            'grade_level_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($studentSelfApplicant): void {
                    $available = GradeLevel::query()
                        ->whereKey($value)
                        ->where('is_active', true)
                        ->availableForApplicant($studentSelfApplicant)
                        ->exists();

                    if (! $available) {
                        $fail($studentSelfApplicant
                            ? 'Select an available Junior High School grade level.'
                            : 'Junior High School applications require an invited Student login.');
                    }
                },
            ],
            'student_category' => ['required', Rule::in(['regular', 'transferee', 'support_review'])],
            'previous_school_name' => [Rule::requiredIf($this->input('student_category') === 'transferee'), 'nullable', 'string', 'max:255'],
            'previous_school_address' => [Rule::requiredIf($this->input('student_category') === 'transferee'), 'nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'lrn' => 'Learner Reference Number (LRN)',
            'student_first_name' => 'learner first name',
            'student_middle_name' => 'learner middle name',
            'student_last_name' => 'learner last name',
            'birth_date' => 'birth date',
            'birth_place' => 'place of birth',
            'ip_group' => 'Indigenous Peoples group',
            'is_4ps_recipient' => '4Ps beneficiary status',
            'four_ps_household_id' => '4Ps household ID',
            'is_learner_with_disability' => 'learner-with-disability status',
            'guardian_mobile' => 'guardian contact number',
        ];
    }
}
