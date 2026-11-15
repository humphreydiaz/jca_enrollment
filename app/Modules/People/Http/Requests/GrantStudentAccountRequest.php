<?php

namespace App\Modules\People\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GrantStudentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'registrar']) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500', 'not_in:n/a,N/A,na,NA,none,None'],
            'requested_in_person' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Write who asked for the student account (for example, “Requested by the mother, Lorna Reyes, at the Registrar’s office”).',
            'reason.min' => 'Write who asked for the student account (at least 10 characters).',
            'requested_in_person.accepted' => 'Confirm that the parent or guardian asked for this in person.',
        ];
    }
}
