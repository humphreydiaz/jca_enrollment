<?php

namespace App\Modules\Grading\Http\Requests;

use App\Modules\Grading\Models\StudentSubjectGrade;
use Illuminate\Foundation\Http\FormRequest;

class ApproveClassesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', StudentSubjectGrade::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'classes' => ['required', 'array', 'min:1', 'max:200'],
            'classes.*' => ['required', 'string', 'distinct', 'regex:/^[0-9A-Za-z]{26}:[0-9A-Za-z]{26}$/'],
        ];
    }

    public function messages(): array
    {
        return ['classes.required' => 'Select at least one reviewed class to approve.'];
    }
}
