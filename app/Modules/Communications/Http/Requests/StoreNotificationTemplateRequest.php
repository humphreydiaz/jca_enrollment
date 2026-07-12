<?php

namespace App\Modules\Communications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return ['code' => ['required', 'alpha_dash', 'max:80'], 'purpose' => ['required', 'in:transactional,announcement'], 'content' => ['required', 'string', 'max:480'], 'is_active' => ['required', 'boolean']];
    }
}
