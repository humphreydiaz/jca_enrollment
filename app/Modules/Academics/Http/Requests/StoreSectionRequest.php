<?php

namespace App\Modules\Academics\Http\Requests;

use Illuminate\Validation\Rule;

class StoreSectionRequest extends AcademicCatalogRequest
{
    public function rules(): array
    {
        return $this->sectionRules(true);
    }

    /** @return array<string, array<int, mixed>> */
    protected function sectionRules(bool $activeParents): array
    {
        $section = $this->route('section');
        $yearRule = Rule::exists('academic_years', 'id');
        $gradeRule = Rule::exists('grade_levels', 'id');

        if ($activeParents) {
            $yearRule->whereIn('status', ['planned', 'open']);
            $gradeRule->where('is_active', true);
        }

        return [
            'academic_year_id' => ['required', 'integer', $yearRule],
            'grade_level_id' => ['required', 'integer', $gradeRule],
            'name' => ['required', 'string', 'max:100', Rule::unique('sections', 'name')->where(fn ($query) => $query
                ->where('academic_year_id', $this->input('academic_year_id'))
                ->where('grade_level_id', $this->input('grade_level_id')))->ignore($section)],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'activity_schedule' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
