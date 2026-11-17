<?php

namespace App\Modules\Academics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Admin-only, explicitly confirmed deletion of an unused academic catalog record. */
class DeleteAcademicRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return ['confirm_deletion' => ['required', 'accepted']];
    }

    public function messages(): array
    {
        return ['confirm_deletion.required' => 'Tick the confirmation box to delete this record.', 'confirm_deletion.accepted' => 'Tick the confirmation box to delete this record.'];
    }
}
