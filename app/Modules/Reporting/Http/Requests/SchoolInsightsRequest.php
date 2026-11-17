<?php

namespace App\Modules\Reporting\Http\Requests;

use App\Modules\Academics\Models\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;

class SchoolInsightsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'accounting']) ?? false;
    }

    public function rules(): array
    {
        return ['year' => ['nullable', 'ulid', 'exists:academic_years,public_id']];
    }

    /** The chosen year, else the open year, else the newest one. */
    public function academicYear(): ?AcademicYear
    {
        $chosen = $this->validated('year');
        if ($chosen !== null) {
            return AcademicYear::query()->where('public_id', $chosen)->first();
        }

        return AcademicYear::query()->where('status', 'open')->orderByDesc('starts_on')->first()
            ?? AcademicYear::query()->orderByDesc('starts_on')->first();
    }
}
