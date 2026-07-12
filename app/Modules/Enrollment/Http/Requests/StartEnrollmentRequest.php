<?php

namespace App\Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['guardian_first_name' => ['required', 'string', 'max:100'], 'guardian_last_name' => ['required', 'string', 'max:100'], 'guardian_mobile' => ['nullable', 'string', 'max:30', 'required_without:guardian_email'], 'guardian_email' => ['nullable', 'email', 'max:255', 'required_without:guardian_mobile'], 'student_first_name' => ['required', 'string', 'max:100'], 'student_last_name' => ['required', 'string', 'max:100'], 'birth_date' => ['required', 'date', 'before:today'], 'academic_year_id' => ['required', Rule::exists('academic_years', 'id')->whereIn('status', ['planned', 'open'])], 'grade_level_id' => ['required', Rule::exists('grade_levels', 'id')->where('is_active', true)], 'student_category' => ['required', Rule::in(['regular', 'transferee', 'support_review'])]];
    }
}
