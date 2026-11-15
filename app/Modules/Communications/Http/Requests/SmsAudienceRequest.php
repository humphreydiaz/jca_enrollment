<?php

namespace App\Modules\Communications\Http\Requests;

use App\Modules\Communications\Models\AnnouncementAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** D-052: "who would get this text?" before an announcement SMS is sent, and the people search. */
class SmsAudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'everyone' => ['boolean'],
            'audience_roles' => ['nullable', 'array'],
            'audience_roles.*' => [Rule::in(array_keys(AnnouncementAudience::ROLES))],
            'sms_body' => ['nullable', 'string', 'max:2000'],
            'search' => ['nullable', 'string', 'max:100'],
            'group' => ['nullable', Rule::in(array_keys(AnnouncementAudience::ROLES))],
        ];
    }

    /** @return list<string> */
    public function roles(): array
    {
        return $this->boolean('everyone') ? array_keys(AnnouncementAudience::ROLES) : array_values(array_unique($this->validated('audience_roles') ?? []));
    }
}
