<?php

namespace App\Modules\People\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkStudentAccountRequest extends FormRequest
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
            'user_public_id' => ['required', 'ulid', 'exists:users,public_id'],
            'identity_confirmed' => ['accepted'],
            'exception_reason' => ['nullable', 'string', 'min:10', 'max:2000', 'not_in:N/A,n/a,NA,na,None,none'],
        ];
    }
}
