<?php

namespace App\Modules\Academics\Http\Requests;

use Illuminate\Validation\Rule;

class GradeLevelRequest extends AcademicCatalogRequest
{
    public function rules(): array
    {
        $gradeLevel = $this->route('gradeLevel');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('grade_levels', 'code')->ignore($gradeLevel)],
            'name' => ['required', 'string', 'max:255'],
            'sequence' => ['required', 'integer', 'min:1', Rule::unique('grade_levels', 'sequence')->ignore($gradeLevel)],
            'school_stage' => ['nullable', 'string', 'max:50'],
            'next_grade_level_id' => ['nullable', 'integer', Rule::exists('grade_levels', 'id')->where('is_active', true)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
