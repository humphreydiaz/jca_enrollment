<?php

namespace App\Modules\Enrollment\Http\Requests;

use App\Modules\Academics\Models\GradeLevel;
use App\Modules\People\Support\LearnerProfileOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueWalkInAccessRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'guardian_mobile' => filled($this->input('guardian_mobile'))
                ? trim((string) $this->input('guardian_mobile'))
                : null,

            'guardian_email' => filled($this->input('guardian_email'))
                ? trim((string) $this->input('guardian_email'))
                : null,
        ]);
    }

    public function guardianOwned(): bool
    {
        $grade = GradeLevel::query()
            ->find($this->input('grade_level_id'));

        return $grade !== null
            && ! $grade->allowsStudentAccountByDefault();
    }

    public function isJuniorHigh(): bool
    {
        $grade = GradeLevel::query()
            ->find($this->input('grade_level_id'));

        return in_array(
            $grade?->code,
            ['G7', 'G8', 'G9', 'G10'],
            true
        );
    }

    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            'admin',
            'registrar',
        ]) ?? false;
    }

    public function rules(): array
    {
        $guardianRequired = $this->guardianOwned()
            || $this->isJuniorHigh();

        $isJuniorHigh = $this->isJuniorHigh();

        return [
            'academic_year_id' => [
                'required',
                Rule::exists('academic_years', 'id')
                    ->whereIn('status', ['planned', 'open']),
            ],

            'grade_level_id' => [
                'required',
                Rule::exists('grade_levels', 'id')
                    ->where('is_active', true),
            ],

            'student_category' => [
                'required',
                Rule::in([
                    'regular',
                    'transferee',
                    'support_review',
                ]),
            ],

            'lrn' => ['nullable', 'digits:12'],

            'student_first_name' => [
                'required', 'string', 'max:100',
            ],

            'student_middle_name' => [
                'nullable', 'string', 'max:100',
            ],

            'student_last_name' => [
                'required', 'string', 'max:100',
            ],

            'extension_name' => [
                'nullable', 'string', 'max:30',
            ],

            'sex' => [
                'required',
                Rule::in(
                    array_keys(LearnerProfileOptions::SEXES)
                ),
            ],

            'birth_date' => [
                'required', 'date', 'before:today',
            ],

            ...($guardianRequired ? [
                'guardian_first_name' => [
                    'required', 'string', 'max:100',
                ],

                'guardian_middle_name' => [
                    'nullable', 'string', 'max:100',
                ],

                'guardian_last_name' => [
                    'required', 'string', 'max:100',
                ],

                'guardian_extension_name' => [
                    'nullable', 'string', 'max:30',
                ],

                'guardian_relationship' => [
                    'required',
                    Rule::in(
                        array_keys(
                            LearnerProfileOptions::GUARDIAN_RELATIONSHIPS
                        )
                    ),
                ],

                'guardian_mobile' => [
                    $isJuniorHigh ? 'required' : 'nullable',
                    'string',
                    'max:30',
                    'regex:/^(?:\+63|0)9\d{9}$/',
                ],

                'guardian_email' => [
                    'nullable',
                    'email:rfc',
                    'max:255',
                ],
            ] : []),

            'received_document_types' => [
                'sometimes',
                'array',
            ],

            'received_document_types.*' => [
                'integer',
                'distinct',
                Rule::exists('document_types', 'id'),
            ],

            ...($isJuniorHigh ? [
                'identity_confirmed' => [
                    'required',
                    'accepted',
                ],
            ] : []),
        ];
    }
}
