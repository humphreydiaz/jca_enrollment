<?php

namespace App\Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class AcademicCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'registrar']) ?? false;
    }
}
