<?php

namespace App\Modules\Communications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:1000'],
            'status' => ['required', 'in:draft,published'],
            'published_at' => ['nullable', 'date'],
            'publish_until' => ['nullable', 'date', 'after:published_at'],
            'sms_template_public_id' => ['nullable', 'ulid'],
            'audiences' => ['required', 'array', 'min:1', 'max:20'],
            'audiences.*.type' => ['required', 'in:all,role,section,student,guardian'],
            'audiences.*.role_code' => ['nullable', 'in:admin,registrar,accounting,teacher,guardian,student'],
            'audiences.*.section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'audiences.*.student_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'audiences.*.guardian_id' => ['nullable', 'integer', 'exists:guardian_profiles,id'],
        ];
    }
}
