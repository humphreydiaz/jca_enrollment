<?php

namespace App\Modules\Academics\Http\Requests;

class UpdateTeachingAssignmentRequest extends AcademicCatalogRequest
{
    public function rules(): array
    {
        return [
            'active_from' => ['nullable', 'date'],
            'active_until' => ['nullable', 'date', 'after_or_equal:active_from'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
