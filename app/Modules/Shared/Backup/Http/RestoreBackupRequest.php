<?php

namespace App\Modules\Shared\Backup\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** D-060: restoring replaces every record, so the Admin confirms three ways: a checkbox, typing RESTORE, and their password. */
class RestoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-backups');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'acknowledge' => ['accepted'],
            'confirmation' => ['required', 'string', 'in:RESTORE'],
            'password' => ['required', 'string', 'current_password'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'acknowledge.accepted' => 'Tick the box to confirm you understand that every record will be replaced.',
            'confirmation.in' => 'Type RESTORE in capital letters to confirm.',
            'password.current_password' => 'The password is not correct.',
        ];
    }
}
