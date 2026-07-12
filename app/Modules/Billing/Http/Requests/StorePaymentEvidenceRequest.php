<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StorePaymentEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('accounting') ?? false;
    }

    public function rules(): array
    {
        return ['evidence' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(5 * 1024)]];
    }
}
