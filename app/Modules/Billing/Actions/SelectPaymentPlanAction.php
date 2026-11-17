<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Calculations\DueScheduleCalculator;
use App\Modules\Billing\Calculations\Money;
use App\Modules\Billing\Models\ExamPeriod;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\PaymentPlan;
use App\Modules\Billing\Models\PaymentPlanOption;
use App\Modules\Billing\Models\SchoolNonBusinessDay;
use App\Modules\Shared\Audit\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SelectPaymentPlanAction
{
    public function __construct(private DueScheduleCalculator $calculator, private AuditRecorder $audit) {}

    public function execute(FeeAssessment $assessment, string $planType, User $actor): PaymentPlan
    {
        return DB::transaction(function () use ($assessment, $planType, $actor): PaymentPlan {
            $locked = FeeAssessment::query()->with(['items', 'enrollment.academicYear'])->lockForUpdate()->findOrFail($assessment->id);
            if ($locked->status !== 'finalized') {
                throw ValidationException::withMessages(['assessment' => 'A payment plan requires a finalized assessment.']);
            }
            $configuredOptions = PaymentPlanOption::query()->where('academic_year_id', $locked->enrollment->academic_year_id)
                ->where(fn ($query) => $query->whereNull('grade_level_id')->orWhere('grade_level_id', $locked->enrollment->grade_level_id));
            if ($configuredOptions->exists() && ! (clone $configuredOptions)->where('code', $planType)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['plan_type' => 'That payment plan is not enabled for this grade and school year.']);
            }

            $tuition = $locked->items->where('fee_code', 'TUI');
            $other = $locked->items->where('fee_code', '!=', 'TUI');
            $examStarts = ExamPeriod::query()->where('academic_year_id', $locked->enrollment->academic_year_id)->orderBy('starts_on')->pluck('starts_on')->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())->all();
            $holidays = SchoolNonBusinessDay::query()->where('academic_year_id', $locked->enrollment->academic_year_id)->pluck('calendar_date')->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())->all();

            $requiredExamPeriods = match ($planType) {
                'quarterly' => 4,
                'semiannual' => 3,
                default => 0,
            };
            if (count($examStarts) < $requiredExamPeriods) {
                $label = $planType === 'quarterly' ? 'Quarterly' : 'Semiannual';

                throw ValidationException::withMessages([
                    'plan_type' => "{$label} schedules require configured examination periods. Ask an Admin to enter the official examination dates before generating this due schedule.",
                ]);
            }

            $rows = $this->calculator->calculate(
                $planType,
                Money::sum($tuition->pluck('net_amount')),
                Money::sum($other->pluck('net_amount')),
                Money::sum($tuition->where('is_permit_critical', true)->pluck('net_amount')),
                Money::sum($other->where('is_permit_critical', true)->pluck('net_amount')),
                $locked->enrollment->academicYear->starts_on->toDateString(),
                $examStarts,
                $holidays,
            );

            $plan = PaymentPlan::query()->updateOrCreate(
                ['fee_assessment_id' => $locked->id],
                ['plan_type' => $planType, 'status' => 'active', 'selected_by' => $actor->id, 'selected_at' => now()],
            );
            $plan->scheduleItems()->delete();
            foreach ($rows as $row) {
                $plan->scheduleItems()->create(['installment_number' => $row['number'], 'label' => $row['label'], 'due_on' => $row['due_on'], 'amount' => $row['amount'], 'permit_critical_amount' => $row['permit_critical_amount']]);
            }
            $this->audit->record($actor, 'billing.plan.selected', $plan, ['plan_type' => $planType]);

            return $plan->load('scheduleItems');
        });
    }
}
