<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Models\FeeAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', FeeAssessment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'received_on' => ['required', 'date'],
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'method' => ['required', Rule::in(['cash', 'cheque', 'bank_transfer', 'e_wallet', 'other'])],
            'payer_name' => ['required', 'string', 'max:255'],
            'method_reference' => ['nullable', 'required_unless:method,cash', 'string', 'max:100', Rule::unique('payments')->where(fn ($query) => $query->where('method', $this->input('method')))->ignore($this->route('payment'))],
            'bank_name' => ['nullable', 'required_if:method,cheque', 'string', 'max:100'],
            'method_description' => ['nullable', 'required_if:method,other', 'string', 'max:100'],
            'assessment_item_id' => ['nullable', 'integer', 'exists:assessment_items,id'],
            'card_number' => ['prohibited'],
            'account_number' => ['prohibited'],
            'pin' => ['prohibited'],
            'cvv' => ['prohibited'],
            'password' => ['prohibited'],
        ];
    }
}
