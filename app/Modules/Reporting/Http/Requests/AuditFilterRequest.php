<?php

namespace App\Modules\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AuditFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            'admin',
            'registrar',
        ]) ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'ulid', 'exists:users,public_id'],
            'subject_reference' => ['nullable', 'string', 'max:64'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = [
                'action',
                'actor',
                'subject_reference',
                'date_from',
                'date_to',
                'page',
            ];

            foreach (array_keys($this->query->all()) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add(
                        'filters',
                        'An unsupported audit filter was supplied.'
                    );
                }
            }
        }];
    }
}