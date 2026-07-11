<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueRecoveryInvitationRequest extends FormRequest
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
        return [];
    }
}
