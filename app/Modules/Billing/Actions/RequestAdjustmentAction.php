<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\FinancialAdjustment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

final class RequestAdjustmentAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(FeeAssessment $assessment, array $data, User $actor): FinancialAdjustment
    {
        if (isset($data['assessment_item_id']) && ! $assessment->items()->whereKey($data['assessment_item_id'])->exists()) {
            throw ValidationException::withMessages(['assessment_item_id' => 'The adjustment target does not belong to this assessment.']);
        }
        $adjustment = FinancialAdjustment::query()->create([...$data, 'fee_assessment_id' => $assessment->id, 'requested_by' => $actor->id, 'status' => 'requested']);
        $this->audit->record($actor, 'billing.adjustment.requested', $adjustment, ['adjustment_type' => $adjustment->adjustment_type]);

        return $adjustment;
    }
}
