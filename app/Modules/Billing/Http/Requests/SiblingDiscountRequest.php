<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** D-067: confirm (no fields) or withdraw (reason required) a sibling discount confirmation. */
class SiblingDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'registrar']) ?? false;
    }

    public function rules(): array
    {
        return $this->isMethod('delete')
            ? ['reason' => ['required', 'string', 'min:5', 'max:500']]
            : [];
    }

    public function attributes(): array
    {
        return ['reason' => 'reason for withdrawing'];
    }
}
