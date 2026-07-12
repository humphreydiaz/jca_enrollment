<?php

namespace App\Modules\Academics\Http\Requests;

class UpdateSectionSubjectRequest extends AcademicCatalogRequest
{
    public function rules(): array
    {
        return ['schedule_text' => ['nullable', 'string', 'max:2000']];
    }
}
