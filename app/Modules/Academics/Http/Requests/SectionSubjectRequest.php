<?php

namespace App\Modules\Academics\Http\Requests;

use Illuminate\Validation\Rule;

class SectionSubjectRequest extends AcademicCatalogRequest
{
    public function rules(): array
    {
        $sectionSubject = $this->route('sectionSubject');

        return [
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->where('status', 'active')],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('is_active', true), Rule::unique('section_subjects', 'subject_id')->where(fn ($query) => $query->where('section_id', $this->input('section_id')))],
            'schedule_text' => ['nullable', 'string', 'max:2000'],
            'section_subject_id' => ['nullable'],
        ];
    }
}
