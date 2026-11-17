<?php

namespace App\Modules\Grading\Http\Requests;

use App\Modules\Grading\Models\StudentSubjectGrade;
use Illuminate\Foundation\Http\FormRequest;

/** "Review all": the class keys and grade fingerprints that were on screen. */
class ReviewClassesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', StudentSubjectGrade::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'seen' => ['required', 'array', 'min:1', 'max:50', function (string $attribute, mixed $value, \Closure $fail): void {
                foreach (array_keys((array) $value) as $key) {
                    if (! is_string($key) || preg_match('/^[0-9A-Za-z]{26}:[0-9A-Za-z]{26}$/', $key) !== 1) {
                        $fail('The reviewed classes were not recognised. Reload the page and try again.');

                        return;
                    }
                }
            }],
            'seen.*' => ['required', 'array', 'max:100'],
            'seen.*.*' => ['required', 'string', 'regex:/^[0-9A-Za-z]{26}:[0-9a-f]{64}$/'],
        ];
    }
}
