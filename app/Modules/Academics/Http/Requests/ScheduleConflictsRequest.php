<?php

namespace App\Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleConflictsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'registrar']) ?? false;
    }

    public function rules(): array
    {
        return ['year' => ['nullable', 'string', 'size:26', 'alpha_num']];
    }
}
