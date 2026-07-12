<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class InvitationPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<Rule|string>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ];
    }
}
