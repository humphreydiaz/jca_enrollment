<?php

namespace App\Modules\Academics\Http\Requests;

use App\Modules\Academics\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectionSubjectStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('archive', Section::class) ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['active', 'archived'])]];
    }
}
