<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestReversalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('accounting') ?? false;
    }

    public function rules(): array
    {
        return ['amount' => ['required', 'decimal:0,2', 'gt:0'], 'reason' => ['required', 'string', 'max:2000']];
    }
}
