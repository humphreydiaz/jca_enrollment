<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\ExamPeriod;
use App\Modules\Billing\Models\ExamPermitOverride;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

final class RequestPermitOverrideAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(Enrollment $enrollment, array $data, User $actor): ExamPermitOverride
    {
        if (isset($data['exam_period_id']) && ! ExamPeriod::query()->whereKey($data['exam_period_id'])->where('academic_year_id', $enrollment->academic_year_id)->exists()) {
            throw ValidationException::withMessages(['exam_period_id' => 'The exam period does not belong to this school year.']);
        }
        $override = ExamPermitOverride::query()->create([...$data, 'enrollment_id' => $enrollment->id, 'status' => 'requested', 'requested_by' => $actor->id, 'verified_by' => $actor->id, 'verified_at' => now()]);
        $this->audit->record($actor, 'billing.permit_override.requested', $override, ['reason_category' => $override->reason_category]);

        return $override;
    }
}
