<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<Rule|string>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ];
    }
}
