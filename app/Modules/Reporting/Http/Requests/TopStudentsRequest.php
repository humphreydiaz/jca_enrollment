<?php

namespace App\Modules\Reporting\Http\Requests;

use App\Modules\Academics\Models\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TopStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'registrar']) ?? false;
    }

    public function rules(): array
    {
        return [
            'year' => ['nullable', 'ulid', 'exists:academic_years,public_id'],
            'group' => ['nullable', Rule::in(['grade', 'section'])],
            'basis' => ['nullable', Rule::in(['final', 'projected'])],
        ];
    }

    public function academicYear(): ?AcademicYear
    {
        $chosen = $this->validated('year');

        return $chosen !== null
            ? AcademicYear::query()->where('public_id', $chosen)->first()
            : (AcademicYear::query()->where('status', 'open')->orderByDesc('starts_on')->first() ?? AcademicYear::query()->orderByDesc('starts_on')->first());
    }

    public function group(): string
    {
        return $this->validated('group') ?? 'grade';
    }

    public function basis(): string
    {
        return $this->validated('basis') ?? 'final';
    }
}
