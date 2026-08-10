<?php

namespace App\Modules\Requirements\Http\Requests;

use App\Modules\Enrollment\Models\EnrollmentRequirement;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHardCopyReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', EnrollmentRequirement::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'received' => ['required', 'boolean'],
            'receipt_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
