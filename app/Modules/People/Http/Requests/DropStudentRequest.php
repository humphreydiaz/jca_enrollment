<?php

namespace App\Modules\People\Http\Requests;

use App\Modules\People\Models\StudentProfile;
use Illuminate\Foundation\Http\FormRequest;

class DropStudentRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $errorBag = 'dropStudent';

    public function authorize(): bool
    {
        return $this->user()?->can('manage', StudentProfile::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'effective_on' => ['required', 'date', 'before_or_equal:today'],
            'drop_category' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Modules\People\Support\StudentExitOptions::DROP_CATEGORIES))],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'lis_dropped' => ['accepted'],
            'confirm_drop' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'effective_on' => 'drop date',
            'drop_category' => 'kind of drop',
            'reason' => 'reason for dropping',
            'lis_dropped' => 'DepEd LIS confirmation',
            'confirm_drop' => 'confirmation',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lis_dropped.accepted' => 'Drop the learner in DepEd LIS first, then tick this box. Dropping is done directly in LIS.',
            'confirm_drop.accepted' => 'Tick the confirmation box to mark this learner as Dropped.',
        ];
    }
}
