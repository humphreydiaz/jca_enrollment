<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('accounting') ?? false;
    }

    public function rules(): array
    {
        return ['assessment_item_id' => ['nullable', 'integer', 'exists:assessment_items,id'], 'adjustment_type' => ['required', Rule::in(['debit', 'credit', 'waiver'])], 'amount' => ['required', 'decimal:0,2', 'gt:0'], 'reason' => ['required', 'string', 'max:2000']];
    }
}
