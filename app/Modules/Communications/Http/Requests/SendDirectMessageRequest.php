<?php

namespace App\Modules\Communications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** D-052: the Admin texts the people they ticked. */
class SendDirectMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'recipients' => ['required', 'array', 'min:1', 'max:200'],
            'recipients.*' => ['required', 'string', 'regex:/^(guardian|student|user):[0-9A-HJKMNP-TV-Z]{26}$/i'],
            'body' => ['required', 'string', 'max:480', StoreAnnouncementRequest::smsLength()],
        ];
    }

    public function messages(): array
    {
        return ['recipients.required' => 'Tick at least one person to message.', 'body.required' => 'Write the text message.'];
    }
}
