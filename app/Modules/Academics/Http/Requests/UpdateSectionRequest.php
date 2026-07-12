<?php

namespace App\Modules\Academics\Http\Requests;

class UpdateSectionRequest extends StoreSectionRequest
{
    public function rules(): array
    {
        return $this->sectionRules(false);
    }
}
