<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Calculations\PermitEligibilityCalculator;
use App\Modules\Billing\Models\ExamPeriod;
use App\Modules\Billing\Models\ExamPermit;
use App\Modules\Billing\Models\ExamPermitCalculation;
use App\Modules\Billing\Models\ExamPermitOverride;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class IssueExamPermitAction
{
    public function __construct(
        private PermitEligibilityCalculator $calculator,
        private NextFinancialReferenceAction $reference,
        private AuditRecorder $audit
    ) {}

    public function execute(
        FeeAssessment $assessment,
        ExamPeriod $period,
        User $actor
    ): ExamPermit {
        if (! $actor->hasRole('accounting') && ! $actor->hasRole('admin')) {
            throw ValidationException::withMessages([
                'permit' => 'Only Accounting or Administrator may issue an eligible permit.',
            ]);
        }

        if ($period->academic_year_id !== $assessment->enrollment->academic_year_id) {
            throw ValidationException::withMessages([
                'period' => 'The exam period does not belong to this enrollment school year.',
            ]);
        }

        // Issuance opens on the first day of the examination month.
        // Issuance closes automatically when the first exam day begins.
        $today = today();
        $opensOn = $period->starts_on->copy()->startOfMonth();
        $examStartsOn = $period->starts_on->copy()->startOfDay();

        if ($today->lt($opensOn)) {
            throw ValidationException::withMessages([
                'permit' => 'Permit issuance is not yet open. It opens on the first day of the examination month.',
            ]);
        }

        if ($today->gte($examStartsOn)) {
            throw ValidationException::withMessages([
                'permit' => 'Permit issuance is closed. New permits cannot be issued on or after the first examination day.',
            ]);
        }

        $result = $this->calculator->calculate(
            $assessment->loadMissing('plan.scheduleItems'),
            $period
        );

        $override = ExamPermitOverride::query()
            ->where('enrollment_id', $assessment->enrollment_id)
            ->where('status', 'approved')
            ->where(function ($query) use ($period) {
                $query->whereNull('exam_period_id')
                    ->orWhere('exam_period_id', $period->id);
            })
            ->whereDate('effective_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('expires_on')
                    ->orWhereDate('expires_on', '>=', $today);
            })
            ->latest('approved_at')
            ->first();

        $calculation = ExamPermitCalculation::query()->create([
            'enrollment_id' => $assessment->enrollment_id,
            'exam_period_id' => $period->id,
            'fee_assessment_id' => $assessment->id,
            'required_amount' => $result['required'],
            'satisfied_amount' => $result['satisfied'],
            'ordinary_eligible' => $result['eligible'],
            'override_eligible' => $override !== null,
            'calculated_at' => now(),
            'calculated_by' => $actor->id,
        ]);

        $this->audit->record(
            $actor,
            'billing.permit.calculated',
            $calculation,
            [
                'eligible' => $result['eligible'] || $override !== null,
            ]
        );

        if (! $result['eligible'] && ! $override) {
            throw ValidationException::withMessages([
                'permit' => 'Permit-critical obligations due by the cutoff are not satisfied.',
            ]);
        }

        return DB::transaction(function () use (
            $assessment,
            $period,
            $actor,
            $result,
            $override
        ): ExamPermit {
            $existing = ExamPermit::query()
                ->where('enrollment_id', $assessment->enrollment_id)
                ->where('exam_period_id', $period->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($existing?->status === 'issued') {
                return $existing;
            }

            $permit = ExamPermit::query()->create([
                'permit_reference' => $this->reference->execute(
                    'permit',
                    'JCA-PERMIT'
                ),
                'enrollment_id' => $assessment->enrollment_id,
                'exam_period_id' => $period->id,
                'fee_assessment_id' => $assessment->id,
                'override_id' => $override?->id,
                'replaces_permit_id' => $existing?->id,
                'required_amount' => $result['required'],
                'satisfied_amount' => $result['satisfied'],
                'calculated_at' => now(),
                'issued_at' => now(),
                'issued_by' => $actor->id,
                'status' => 'issued',
                'active_permit_key' => $assessment->enrollment_id . ':' . $period->id,
            ]);

            $this->audit->record(
                $actor,
                'billing.permit.issued',
                $permit
            );

            return $permit;
        });
    }
}
