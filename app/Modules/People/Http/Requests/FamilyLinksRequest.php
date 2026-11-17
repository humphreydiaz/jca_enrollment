<?php

namespace App\Modules\People\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FamilyLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'registrar']) ?? false;
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100']];
    }
}
