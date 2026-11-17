<?php

namespace App\Modules\Communications\Http\Requests;

use App\Modules\Identity\Actions\ContactNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class SendTestSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return ['test_mobile' => ['required', 'string', 'regex:/^(?:\+63|0)9\d{9}$/']];
    }

    public function messages(): array
    {
        return ['test_mobile.regex' => 'Use a Philippine mobile number: 09XXXXXXXXX or +639XXXXXXXXX.'];
    }

    public function mobile(): string
    {
        return (string) ContactNormalizer::mobile($this->validated('test_mobile'));
    }
}
