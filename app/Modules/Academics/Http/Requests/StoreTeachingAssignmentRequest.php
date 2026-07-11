<?php

namespace App\Modules\Academics\Http\Requests;

use App\Modules\Academics\Models\SectionSubject;
use App\Modules\People\Models\StaffProfile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTeachingAssignmentRequest extends AcademicCatalogRequest
{
    public function rules(): array
    {
        return [
            'staff_profile_id' => ['required', 'integer', Rule::exists('staff_profiles', 'id')],
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->where('status', 'active')],
            'section_subject_id' => ['nullable', 'integer', Rule::exists('section_subjects', 'id')],
            'assignment_type' => ['required', Rule::in(['adviser', 'subject_teacher'])],
            'active_from' => ['nullable', 'date'],
            'active_until' => ['nullable', 'date', 'after_or_equal:active_from'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $staff = StaffProfile::query()->find($this->integer('staff_profile_id'));
            if ($staff === null || ($staff->active_until !== null && $staff->active_until->isPast())) {
                $validator->errors()->add('staff_profile_id', 'Select an active staff profile.');
            }

            if ($this->input('assignment_type') === 'adviser' && $this->filled('section_subject_id')) {
                $validator->errors()->add('section_subject_id', 'An adviser assignment cannot be linked to a subject.');
            }

            if ($this->input('assignment_type') === 'subject_teacher') {
                $sectionSubject = SectionSubject::query()->find($this->integer('section_subject_id'));
                if ($sectionSubject === null || $sectionSubject->section_id !== $this->integer('section_id')) {
                    $validator->errors()->add('section_subject_id', 'Select a subject configured for the selected section.');
                }
            }
        });
    }
}
