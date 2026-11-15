<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetPaymentPlanOptionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'accounting']) ?? false;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
