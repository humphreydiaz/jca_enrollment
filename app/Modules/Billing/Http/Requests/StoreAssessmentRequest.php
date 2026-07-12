<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Models\FeeAssessment;
use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', FeeAssessment::class) ?? false;
    }

    public function rules(): array
    {
        return ['fee_schedule_id' => ['required', 'integer', 'exists:fee_schedules,id'], 'selected_items' => ['array'], 'selected_items.*' => ['integer', 'exists:fee_schedule_items,id'], 'revision_reason' => ['nullable', 'string', 'max:1000']];
    }
}
