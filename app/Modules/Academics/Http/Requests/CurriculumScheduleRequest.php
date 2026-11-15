<?php

namespace App\Modules\Academics\Http\Requests;

use App\Modules\Academics\Support\ScheduleRules;
use Illuminate\Validation\Rule;

class CurriculumScheduleRequest extends AcademicCatalogRequest
{
    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('is_active', true)],
            ...ScheduleRules::rules(),
        ];
    }
}
