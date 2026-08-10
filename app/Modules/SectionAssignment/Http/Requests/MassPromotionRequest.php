<?php

namespace App\Modules\SectionAssignment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MassPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'registrar']) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'enrollment_public_ids' => ['required', 'array', 'min:1', 'max:100'],
            'enrollment_public_ids.*' => ['required', 'ulid', 'exists:enrollments,public_id'],
            'target_academic_year_public_id' => ['nullable', 'ulid', 'exists:academic_years,public_id'],
        ];
    }
}
