<?php

namespace App\Modules\Enrollment\Actions;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StartEnrollmentAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(array $data): Enrollment
    {
        return DB::transaction(function () use ($data) {
            $guardian = GuardianProfile::query()->create(['first_name' => $data['guardian_first_name'], 'last_name' => $data['guardian_last_name'], 'mobile' => $data['guardian_mobile'] ?? null, 'email' => $data['guardian_email'] ?? null]);
            $student = StudentProfile::query()->create(['first_name' => $data['student_first_name'], 'last_name' => $data['student_last_name'], 'birth_date' => $data['birth_date'] ?? null]);
            $record = Enrollment::query()->create(['application_number' => 'JCA-'.now()->format('Y').'-'.Str::upper(Str::random(8)), 'student_id' => $student->id, 'academic_year_id' => $data['academic_year_id'], 'grade_level_id' => $data['grade_level_id'], 'applicant_guardian_id' => $guardian->id, 'student_category' => $data['student_category'], 'status' => 'draft', 'active_enrollment_key' => $student->id.':'.$data['academic_year_id']]);
            $this->audit->record(null, 'enrollment.draft.started', $record);

            return $record;
        });
    }
}
