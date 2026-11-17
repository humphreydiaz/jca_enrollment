<?php

namespace App\Modules\Academics\Http\Requests;

/** Validates the school year / grade / section filters used by the Academics screens. */
class AcademicWorkspaceRequest extends AcademicCatalogRequest
{
    public function rules(): array
    {
        return [
            'year' => ['nullable', 'string', 'size:26', 'alpha_num'],
            'grade' => ['nullable', 'string', 'size:26', 'alpha_num'],
            'section' => ['nullable', 'string', 'size:26', 'alpha_num'],
            'archived' => ['nullable', 'boolean'],
        ];
    }
}
