<?php

namespace App\Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteCurriculumDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->route('curriculum')) ?? false;
    }

    public function rules(): array
    {
        return ['confirm_deletion' => ['required', 'accepted']];
    }
}
