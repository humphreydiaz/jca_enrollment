<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeAssessmentAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(FeeAssessment $assessment, User $actor): FeeAssessment
    {
        return DB::transaction(function () use ($assessment, $actor) {
            $record = FeeAssessment::query()->lockForUpdate()->findOrFail($assessment->id);
            if ($record->status !== 'draft') {
                throw ValidationException::withMessages(['assessment' => 'Only a draft may be finalized.']);
            }FeeAssessment::query()->where('enrollment_id', $record->enrollment_id)->where('status', 'finalized')->update(['status' => 'superseded', 'current_finalized_key' => null]);
            $record->update(['status' => 'finalized', 'current_finalized_key' => (string) $record->enrollment_id, 'finalized_by' => $actor->id, 'finalized_at' => now()]);
            $this->audit->record($actor, 'billing.assessment.finalized', $record);

            return $record;
        });
    }
}
