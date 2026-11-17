<?php

namespace App\Modules\Grading\Http\Requests;

use App\Modules\Grading\Models\StudentSubjectGrade;
use Illuminate\Foundation\Http\FormRequest;

class GradeApprovalFilterRequest extends FormRequest
{
    public const TABS = ['waiting', 'missing', 'reopen', 'approved'];

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', StudentSubjectGrade::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'year' => ['nullable', 'ulid', 'exists:academic_years,public_id'],
            'grade' => ['nullable', 'ulid', 'exists:grade_levels,public_id'],
            'section' => ['nullable', 'ulid'],
            'subject' => ['nullable', 'ulid'],
            'teacher' => ['nullable', 'ulid'],
            'search' => ['nullable', 'string', 'max:100'],
            'flagged' => ['nullable', 'boolean'],
            'tab' => ['nullable', 'in:'.implode(',', self::TABS)],
        ];
    }
}
