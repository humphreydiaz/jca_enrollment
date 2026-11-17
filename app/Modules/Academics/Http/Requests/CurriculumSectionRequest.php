<?php

namespace App\Modules\Academics\Http\Requests;

use Illuminate\Validation\Rule;

class CurriculumSectionRequest extends AcademicCatalogRequest
{
    public function rules(): array
    {
        return [
            'grade_level_id' => ['required', 'integer', Rule::exists('grade_levels', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'activity_schedule' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
