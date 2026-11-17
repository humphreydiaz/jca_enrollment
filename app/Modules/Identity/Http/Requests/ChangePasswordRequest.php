<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\People\Support\SharedStudentIdPasswordGuard;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

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

    /**
     * D-048: a parent and child who both sign in with the child's Student ID are told apart by
     * password, so neither may choose the other's (sign-in would fail closed for both).
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $password = $this->input('password');
            if (! $validator->errors()->has('password') && is_string($password)
                && app(SharedStudentIdPasswordGuard::class)->collides($this->user(), $password)) {
                $validator->errors()->add('password', 'Choose a different password. Another account that signs in with the same Student ID already uses it.');
            }
        }];
    }
}
