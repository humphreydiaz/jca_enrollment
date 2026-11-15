<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentPlanOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'accounting']) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
