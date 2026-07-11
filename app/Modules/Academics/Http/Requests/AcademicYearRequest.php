<?php

namespace App\Modules\Academics\Http\Requests;

use Illuminate\Validation\Rule;

class AcademicYearRequest extends AcademicCatalogRequest
{
    public function rules(): array
    {
        $year = $this->route('academicYear');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('academic_years', 'code')->ignore($year)],
            'name' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'enrollment_opens_on' => ['nullable', 'date'],
            'enrollment_closes_on' => ['nullable', 'date', 'after_or_equal:enrollment_opens_on'],
            'status' => ['required', Rule::in(['planned', 'open', 'closed', 'archived'])],
        ];
    }
}
