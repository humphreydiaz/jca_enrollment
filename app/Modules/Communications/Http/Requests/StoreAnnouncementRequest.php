<?php

namespace App\Modules\Communications\Http\Requests;

use App\Modules\Communications\Models\Announcement;
use App\Modules\Communications\Models\AnnouncementAudience;
use App\Modules\Communications\Support\SmsLength;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** D-052: title, message, how it is delivered (portal / SMS / both), and who receives it. */
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
            'delivery' => ['required', Rule::in(array_keys(Announcement::DELIVERIES))],
            'body' => [Rule::requiredIf(fn () => $this->input('delivery') !== 'sms'), 'nullable', 'string', 'max:2000'],
            'sms_body' => [Rule::requiredIf(fn () => in_array($this->input('delivery'), ['sms', 'portal_sms'], true)), 'nullable', 'string', 'max:480', self::smsLength()],
            'status' => ['required', 'in:draft,published'],
            'everyone' => ['boolean'],
            'audience_roles' => [Rule::requiredIf(fn () => ! $this->boolean('everyone')), 'array'],
            'audience_roles.*' => [Rule::in(array_keys(AnnouncementAudience::ROLES))],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Write the message people will read on the portal.',
            'sms_body.required' => 'Write the text message.',
            'audience_roles.required' => 'Choose who receives this announcement, or tick Everyone.',
        ];
    }

    /** @return list<string>|null null means Everyone */
    public function roles(): ?array
    {
        return $this->boolean('everyone') ? null : array_values(array_unique($this->validated('audience_roles') ?? []));
    }

    public static function smsLength(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && SmsLength::parts($value) > SmsLength::MAX_PARTS) {
                $fail('The text message is too long: keep it to '.SmsLength::MAX_PARTS.' SMS parts (about 459 plain characters).');
            }
        };
    }
}
