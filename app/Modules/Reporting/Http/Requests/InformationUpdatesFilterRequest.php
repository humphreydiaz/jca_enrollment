<?php

namespace App\Modules\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InformationUpdatesFilterRequest extends FormRequest
{
    private const ALLOWED = ['account', 'name', 'date_from', 'date_to', 'page'];

    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'registrar']) ?? false;
    }

    public function rules(): array
    {
        return [
            'account' => ['nullable', 'in:student,guardian'],
            'name' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $key) {
                if (! in_array($key, self::ALLOWED, true)) {
                    $validator->errors()->add('filters', 'An unsupported filter was supplied.');
                }
            }
        }];
    }
}
