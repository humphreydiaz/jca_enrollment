<?php

namespace App\Modules\Academics\Http\Requests;

use App\Modules\Academics\Support\OfficialClassProgramTemplate;
use Illuminate\Validation\Rule;

class CreateCurriculumRequest extends AcademicCatalogRequest
{
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->whereIn('status', ['planned', 'open'])],
            'name' => ['required', 'string', 'max:150'],
            'source_curriculum_id' => [
                'nullable',
                Rule::when(
                    $this->input('source_curriculum_id') === OfficialClassProgramTemplate::SOURCE_KEY,
                    [Rule::in([OfficialClassProgramTemplate::SOURCE_KEY])],
                    ['integer', Rule::exists('curricula', 'id')->where('status', 'published')],
                ),
            ],
        ];
    }
}
