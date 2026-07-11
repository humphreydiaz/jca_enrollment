<?php

namespace App\Modules\People\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'user_public_id' => ['nullable', 'ulid', 'exists:users,public_id'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'student_number' => ['nullable', 'string', 'max:50', 'unique:student_profiles,student_number'],
            'birth_date' => ['nullable', 'date', 'before:today'],
        ];
    }
}
