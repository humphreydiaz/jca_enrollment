<?php

namespace App\Modules\Grading\Http\Requests;

use App\Modules\Grading\Models\StudentSubjectGrade;
use Illuminate\Foundation\Http\FormRequest;

class ApproveGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', StudentSubjectGrade::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'grade_ids' => ['required', 'array', 'min:1', 'max:200'],
            'grade_ids.*' => ['required', 'ulid', 'distinct'],
        ];
    }
}
