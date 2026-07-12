<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Calculations\Money;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\FinancialAdjustment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApproveAdjustmentAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(FinancialAdjustment $adjustment, User $admin, bool $approve): FinancialAdjustment
    {
        return DB::transaction(function () use ($adjustment, $admin, $approve): FinancialAdjustment {
            $record = FinancialAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
            if (! $admin->hasRole('admin') || $record->requested_by === $admin->id) {
                throw ValidationException::withMessages(['approval' => 'An independent Admin must decide the adjustment.']);
            }
            if ($record->status !== 'requested') {
                throw ValidationException::withMessages(['adjustment' => 'This adjustment has already been decided.']);
            }
            if (! in_array($record->adjustment_type, ['debit', 'credit', 'waiver'], true) || Money::cents((string) $record->amount) <= 0) {
                throw ValidationException::withMessages(['adjustment' => 'Invalid adjustment type or amount.']);
            }
            if ($approve && in_array($record->adjustment_type, ['credit', 'waiver'], true)) {
                $assessment = FeeAssessment::query()->lockForUpdate()->findOrFail($record->fee_assessment_id);
                $approvedDebits = FinancialAdjustment::query()->where('fee_assessment_id', $assessment->id)->where('status', 'approved')->where('adjustment_type', 'debit')->sum('amount');
                $approvedReductions = FinancialAdjustment::query()->where('fee_assessment_id', $assessment->id)->where('status', 'approved')->whereIn('adjustment_type', ['credit', 'waiver'])->sum('amount');
                $maximum = Money::cents((string) $assessment->net_total) + Money::cents((string) $approvedDebits) - Money::cents((string) $approvedReductions);
                if (Money::cents((string) $record->amount) > $maximum) {
                    throw ValidationException::withMessages(['amount' => 'Credit adjustments and waivers cannot exceed eligible assessed charges.']);
                }
            }
            $record->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $admin->id, 'decided_at' => now()]);
            $this->audit->record($admin, $approve ? 'billing.adjustment.approved' : 'billing.adjustment.rejected', $record, ['adjustment_type' => $record->adjustment_type]);

            return $record;
        });
    }
}
