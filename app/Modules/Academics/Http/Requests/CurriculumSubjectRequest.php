<?php

namespace App\Modules\Academics\Http\Requests;

use Illuminate\Validation\Rule;

class CurriculumSubjectRequest extends AcademicCatalogRequest
{
    public function rules(): array
    {
        return [
            'grade_level_id' => ['required', 'integer', Rule::exists('grade_levels', 'id')->where('is_active', true)],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('is_active', true)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }
}
