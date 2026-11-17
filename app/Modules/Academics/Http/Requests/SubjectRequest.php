<?php

namespace App\Modules\Academics\Http\Requests;

use Illuminate\Validation\Rule;

class SubjectRequest extends AcademicCatalogRequest
{
    public function rules(): array
    {
        $subject = $this->route('subject');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('subjects', 'code')->ignore($subject)],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
