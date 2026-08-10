<?php

namespace App\Modules\People\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteStudentProfileRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'deletion_confirmed' => ['accepted'],
        ];
    }
}
