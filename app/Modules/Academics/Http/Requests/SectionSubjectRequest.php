<?php

namespace App\Modules\Academics\Http\Requests;

use App\Modules\Academics\Support\ScheduleRules;
use Illuminate\Validation\Rule;

class SectionSubjectRequest extends AcademicCatalogRequest
{
    public function rules(): array
    {
        return [
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->where('status', 'active')],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('is_active', true), Rule::unique('section_subjects', 'subject_id')->where(fn ($query) => $query->where('section_id', $this->input('section_id')))],
            ...ScheduleRules::rules(),
        ];
    }

    public function attributes(): array
    {
        return ScheduleRules::attributes();
    }

    public function messages(): array
    {
        return ['subject_id.unique' => 'This section already has that subject. If it was archived, restore it instead.'];
    }
}
