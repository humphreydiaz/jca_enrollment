<?php

namespace App\Modules\People\Http\Requests;

use App\Modules\People\Models\StudentProfile;
use App\Modules\People\Support\StudentExitOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferOutStudentRequest extends FormRequest
{
    /**
     * Errors from this form are kept in their own bag so they never collide with
     * the profile-correction form on the same learner page.
     *
     * @var string
     */
    protected $errorBag = 'transferOut';

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
            'receiving_school' => ['required', 'string', 'min:3', 'max:255'],
            'receiving_school_id' => ['nullable', 'string', 'max:20'],
            'receiving_school_address' => ['nullable', 'string', 'max:500'],
            'effective_on' => ['required', 'date', 'before_or_equal:today'],
            'sf10_release_method' => ['required', Rule::in(array_keys(StudentExitOptions::SF10_RELEASE_METHODS))],
            'sf10_released_on' => ['required', 'date', 'before_or_equal:today'],
            'sf10_released_to' => ['nullable', 'required_if:sf10_release_method,hand_carried', 'string', 'max:150'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm_transfer' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'receiving_school' => 'receiving school',
            'receiving_school_id' => 'DepEd school ID',
            'receiving_school_address' => 'receiving school address',
            'effective_on' => 'transfer date',
            'sf10_release_method' => 'SF10 release method',
            'sf10_released_on' => 'SF10 release date',
            'sf10_released_to' => 'person who hand-carried the SF10',
            'reason' => 'reason or remarks',
            'confirm_transfer' => 'confirmation',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sf10_released_to.required_if' => 'Enter the name of the student or guardian who will hand-carry the SF10.',
            'confirm_transfer.accepted' => 'Tick the confirmation box to mark this learner as Transferred Out.',
        ];
    }
}
