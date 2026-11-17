<?php

namespace App\Modules\People\Http\Requests;

use App\Modules\People\Http\Requests\Concerns\ValidatesIndigenousPeoplesAnswer;
use App\Modules\People\Models\StudentProfile;
use App\Modules\People\Support\LearnerProfileOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentProfileRequest extends FormRequest
{
    use ValidatesIndigenousPeoplesAnswer;

    /** @return array<string, string> */
    public function attributes(): array
    {
        return $this->indigenousPeoplesAttributes();
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manage', StudentProfile::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_4ps_recipient' => $this->boolean('is_4ps_recipient'),
            'is_learner_with_disability' => $this->boolean('is_learner_with_disability'),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'extension_name' => ['nullable', 'string', 'max:30'],
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'sex' => ['nullable', Rule::in(array_keys(LearnerProfileOptions::SEXES))],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'mother_tongue' => ['nullable', 'string', 'max:100'],
            ...$this->indigenousPeoplesRules(),
            'is_4ps_recipient' => ['required', 'boolean'],
            'four_ps_household_id' => [Rule::requiredIf($this->boolean('is_4ps_recipient')), 'nullable', 'string', 'max:50'],
            'is_learner_with_disability' => ['required', 'boolean'],
            'disability_type' => [Rule::requiredIf($this->boolean('is_learner_with_disability')), 'nullable', Rule::in(array_keys(LearnerProfileOptions::DISABILITY_TYPES))],
            'current_address' => ['nullable', 'string', 'max:1000'],
            'permanent_address' => ['nullable', 'string', 'max:1000'],
            'father_first_name' => ['nullable', 'string', 'max:100'],
            'father_middle_name' => ['nullable', 'string', 'max:100'],
            'father_last_name' => ['nullable', 'string', 'max:100'],
            'father_extension_name' => ['nullable', 'string', 'max:30'],
            'mother_first_name' => ['nullable', 'string', 'max:100'],
            'mother_middle_name' => ['nullable', 'string', 'max:100'],
            'mother_maiden_last_name' => ['nullable', 'string', 'max:100'],
            'mother_extension_name' => ['nullable', 'string', 'max:30'],
            'reason' => ['required', 'string', 'min:10', 'max:2000', 'not_regex:/^(?:n\/?a|none|test|unknown)$/i'],
        ];
    }
}
