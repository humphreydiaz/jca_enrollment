<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Models\FeeAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectPaymentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', FeeAssessment::class) ?? false;
    }

    public function rules(): array
    {
        return ['plan_type' => ['required', Rule::in(['full', 'semiannual', 'quarterly', 'monthly'])]];
    }
}
