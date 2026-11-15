<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'channel' => [
                'required',
                Rule::in(['mobile']),
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'mobile' => [
                'required',
                'string',
                'max:30',
            ],

            // Exactly one staff role per invitation.
            'roles' => [
                'required',
                'array',
                'size:1',
            ],

            // Student and Guardian are not allowed.
            'roles.*' => [
                'required',
                'string',
                Rule::in([
                    'admin',
                    'registrar',
                    'accounting',
                    'teacher',
                ]),
            ],

            // Required for all staff accounts.
            'employed_on' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' =>
                'Please enter the staff member full name.',

            'mobile.required' =>
                'Please enter the staff mobile number.',

            'roles.required' =>
                'Please select a staff role.',

            'roles.size' =>
                'Only one staff role can be selected.',

            'roles.*.in' =>
                'Only Admin, Registrar, Accounting, and Teacher roles are allowed.',

            'employed_on.required' =>
                'Employment start date is required.',

            'employed_on.before_or_equal' =>
                'Employment start date cannot be in the future.',
        ];
    }
}
