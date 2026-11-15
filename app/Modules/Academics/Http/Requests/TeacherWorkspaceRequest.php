<?php

namespace App\Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validates the school-year tab used by the teacher's My teaching and Grades pages. */
class TeacherWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('teacher') ?? false;
    }

    public function rules(): array
    {
        return [
            'year' => ['nullable', 'string', 'size:26', 'alpha_num'],
        ];
    }
}
