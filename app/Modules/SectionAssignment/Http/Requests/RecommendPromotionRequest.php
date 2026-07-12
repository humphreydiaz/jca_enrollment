<?php

namespace App\Modules\SectionAssignment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecommendPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'registrar']) ?? false;
    }

    public function rules(): array
    {
        return ['target_academic_year_public_id' => ['nullable', 'ulid', 'exists:academic_years,public_id']];
    }
}
