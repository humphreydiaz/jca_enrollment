<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class LinkGuardianStudentAction
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param  array{relationship_type: string, is_primary_contact?: bool, may_receive_sms?: bool, may_view_financials?: bool, may_manage_enrollment?: bool, has_legal_authority?: bool}  $attributes
     */
    public function execute(GuardianProfile $guardian, StudentProfile $student, array $attributes, User $actor): GuardianStudent
    {
        return DB::transaction(function () use ($actor, $attributes, $guardian, $student): GuardianStudent {
            StudentProfile::query()->lockForUpdate()->findOrFail($student->id);

            if (($attributes['is_primary_contact'] ?? false) === true) {
                GuardianStudent::query()
                    ->where('student_id', $student->id)
                    ->update(['is_primary_contact' => false]);
            }

            $link = GuardianStudent::query()->updateOrCreate(
                ['guardian_id' => $guardian->id, 'student_id' => $student->id],
                [
                    'relationship_type' => $attributes['relationship_type'],
                    'is_primary_contact' => $attributes['is_primary_contact'] ?? false,
                    'may_receive_sms' => $attributes['may_receive_sms'] ?? true,
                    'may_view_financials' => $attributes['may_view_financials'] ?? false,
                    'may_manage_enrollment' => $attributes['may_manage_enrollment'] ?? true,
                    'has_legal_authority' => $attributes['has_legal_authority'] ?? false,
                    'effective_from' => now()->toDateString(),
                    'ended_at' => null,
                ],
            );

            $this->auditRecorder->record($actor, 'people.guardian_student.linked', $student, [
                'guardian_public_id' => $guardian->public_id,
            ]);

            return $link;
        });
    }
}
