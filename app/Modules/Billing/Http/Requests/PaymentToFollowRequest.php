<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentToFollowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['accounting', 'admin']) ?? false;
    }

    public function rules(): array
    {
        return ['approved_until' => ['required', 'date', 'after_or_equal:today'], 'reason' => ['nullable', 'string', 'max:1000']];
    }
}
