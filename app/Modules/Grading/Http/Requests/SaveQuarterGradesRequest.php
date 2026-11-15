<?php

namespace App\Modules\Grading\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveQuarterGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'quarter_1' => ['nullable', 'integer', 'between:0,100'],
            'quarter_2' => ['nullable', 'integer', 'between:0,100'],
            'quarter_3' => ['nullable', 'integer', 'between:0,100'],
            'quarter_4' => ['nullable', 'integer', 'between:0,100'],
        ];
    }
}
