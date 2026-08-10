<?php

namespace App\Modules\SectionAssignment\Http\Requests;

use App\Modules\SectionAssignment\Models\SectionAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSectionAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SectionAssignment::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => filled($this->input('search')) ? trim((string) $this->input('search')) : null,
            'year' => filled($this->input('year')) ? $this->input('year') : null,
            'grade' => filled($this->input('grade')) ? $this->input('grade') : null,
            'section' => filled($this->input('section')) ? $this->input('section') : null,
            'promotion_status' => filled($this->input('promotion_status')) ? $this->input('promotion_status') : null,
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'ulid', 'exists:academic_years,public_id'],
            'grade' => ['nullable', 'ulid', 'exists:grade_levels,public_id'],
            'section' => ['nullable', 'ulid', 'exists:sections,public_id'],
            'promotion_status' => ['nullable', Rule::in(['unreviewed', 'eligible', 'ineligible', 'draft_created'])],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50])],
        ];
    }
}
