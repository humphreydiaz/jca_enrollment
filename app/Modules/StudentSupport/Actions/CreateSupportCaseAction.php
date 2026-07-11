<?php

namespace App\Modules\StudentSupport\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\StudentSupport\Models\SupportCase;
use Illuminate\Validation\ValidationException;

class CreateSupportCaseAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Enrollment $enrollment, User $actor): SupportCase
    {
        if ($enrollment->student_category !== 'support_review') {
            throw ValidationException::withMessages(['enrollment' => 'Only enrollments requiring support review may open a restricted case.']);
        }
        $case = SupportCase::query()->firstOrCreate(['enrollment_id' => $enrollment->id], ['status' => 'open', 'opened_by' => $actor->id, 'opened_at' => now()]);
        $this->audit->record($actor, 'support.case.created', $case);

        return $case;
    }
}
