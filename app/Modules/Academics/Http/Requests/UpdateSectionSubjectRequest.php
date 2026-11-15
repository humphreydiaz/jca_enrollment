<?php

namespace App\Modules\Academics\Http\Requests;

use App\Modules\Academics\Support\ScheduleRules;

class UpdateSectionSubjectRequest extends AcademicCatalogRequest
{
    public function rules(): array
    {
        return ScheduleRules::rules();
    }

    public function attributes(): array
    {
        return ScheduleRules::attributes();
    }
}
