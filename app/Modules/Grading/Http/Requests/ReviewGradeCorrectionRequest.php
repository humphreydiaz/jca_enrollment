<?php

namespace App\Modules\Grading\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewGradeCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['decision' => ['required', 'in:approve,reject'], 'review_note' => ['required', 'string', 'min:10', 'max:1000']];
    }
}
