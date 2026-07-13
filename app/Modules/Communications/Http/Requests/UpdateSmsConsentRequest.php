<?php

namespace App\Modules\Communications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSmsConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('guardian') ?? false;
    }

    public function rules(): array
    {
        return ['transactional' => ['required', 'boolean'], 'announcement' => ['required', 'boolean']];
    }
}
