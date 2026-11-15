<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\Identity\Actions\ContactNormalizer;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\Shared\Audit\AuditValueMask;

class CompleteStudentEnrollmentProfileAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array<string, mixed> $data */
    public function execute(StudentProfile $student, array $data, ?User $actor = null): StudentProfile
    {
        $student->forceFill([
            'lrn' => filled($data['lrn'] ?? null) ? trim($data['lrn']) : null,
            'birth_place' => trim($data['birth_place']),
            'mother_tongue' => trim($data['mother_tongue']),
            'religion' => 'Christianity',
            'ip_group' => filled($data['ip_group'] ?? null) ? trim($data['ip_group']) : null,
            'is_4ps_recipient' => (bool) $data['is_4ps_recipient'],
            'four_ps_household_id' => (bool) $data['is_4ps_recipient'] ? trim($data['four_ps_household_id']) : null,
            'is_learner_with_disability' => (bool) $data['is_learner_with_disability'],
            'disability_type' => (bool) $data['is_learner_with_disability'] ? $data['disability_type'] : null,
            'current_address' => trim($data['current_address']),
            'permanent_address' => (bool) $data['same_as_current_address'] ? trim($data['current_address']) : trim($data['permanent_address']),
            'mobile' => ContactNormalizer::mobile($data['student_mobile'] ?? null),
            'father_first_name' => filled($data['father_first_name'] ?? null) ? trim($data['father_first_name']) : null,
            'father_middle_name' => filled($data['father_middle_name'] ?? null) ? trim($data['father_middle_name']) : null,
            'father_last_name' => filled($data['father_last_name'] ?? null) ? trim($data['father_last_name']) : null,
            'father_extension_name' => filled($data['father_extension_name'] ?? null) ? trim($data['father_extension_name']) : null,
            'mother_first_name' => filled($data['mother_first_name'] ?? null) ? trim($data['mother_first_name']) : null,
            'mother_middle_name' => filled($data['mother_middle_name'] ?? null) ? trim($data['mother_middle_name']) : null,
            'mother_maiden_last_name' => filled($data['mother_maiden_last_name'] ?? null) ? trim($data['mother_maiden_last_name']) : null,
            'mother_extension_name' => filled($data['mother_extension_name'] ?? null) ? trim($data['mother_extension_name']) : null,
        ]);
        $fields = array_keys($student->getDirty());
        $before = array_intersect_key($student->getOriginal(), array_flip($fields));
        $after = $student->only($fields);
        $student->save();

        // D-059: every changed field is named; the learner's own mobile number also keeps masked old/new values.
        $this->audit->recordChanges($actor, 'people.student.application_details_saved', $student, $before, $after, [
            'mobile' => AuditValueMask::MOBILE,
        ]);

        return $student->refresh();
    }
}
