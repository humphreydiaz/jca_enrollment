<?php

namespace App\Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CorrectPreviousSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'registrar']) ?? false;
    }

    public function rules(): array
    {
        return [
            'previous_school_name' => ['nullable', 'string', 'max:255'],
            'previous_school_address' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:10', 'max:2000', 'not_regex:/^(?:n\/?a|none|test|unknown)$/i'],
        ];
    }
}
