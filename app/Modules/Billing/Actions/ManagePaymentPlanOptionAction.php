<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Billing\Models\PaymentPlan;
use App\Modules\Billing\Models\PaymentPlanOption;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManagePaymentPlanOptionAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function create(array $attributes, User $actor): PaymentPlanOption
    {
        return DB::transaction(function () use ($attributes, $actor): PaymentPlanOption {
            AcademicYear::query()->whereKey($attributes['academic_year_id'])->lockForUpdate()->firstOrFail();

            $duplicate = PaymentPlanOption::query()
                ->where('academic_year_id', $attributes['academic_year_id'])
                ->where('code', $attributes['code'])
                ->when(
                    $attributes['grade_level_id'] ?? null,
                    fn ($query, $gradeLevelId) => $query->where('grade_level_id', $gradeLevelId),
                    fn ($query) => $query->whereNull('grade_level_id'),
                )
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'code' => 'This school year and grade already has that payment plan type. Edit its status instead of adding another.',
                ]);
            }

            $option = PaymentPlanOption::query()->create($attributes);
            $this->audit->record($actor, 'billing.payment_plan_option.created', $option);

            return $option;
        });
    }

    public function rename(PaymentPlanOption $option, string $name, User $actor): PaymentPlanOption
    {
        return DB::transaction(function () use ($option, $name, $actor): PaymentPlanOption {
            $locked = PaymentPlanOption::query()->lockForUpdate()->findOrFail($option->id);
            $locked->update(['name' => $name]);
            $this->audit->record($actor, 'billing.payment_plan_option.renamed', $locked);

            return $locked;
        });
    }

    public function delete(PaymentPlanOption $option, User $actor): void
    {
        DB::transaction(function () use ($option, $actor): void {
            $locked = PaymentPlanOption::query()->lockForUpdate()->findOrFail($option->id);

            $usage = $this->usageCount($locked);
            if ($usage > 0) {
                throw ValidationException::withMessages([
                    'plan_option' => "This plan is already used by {$usage} student assessment(s). Deactivate it instead so new selections stop while history is preserved.",
                ]);
            }

            $this->audit->record($actor, 'billing.payment_plan_option.deleted', $locked);
            $locked->delete();
        });
    }

    /**
     * Count the student payment plans derived from this option. There is no
     * foreign key between the two (a plan snapshots the type + schedule at
     * selection time), so usage is matched by plan type within the option's
     * academic year, and grade when the option is grade-specific.
     */
    public function usageCount(PaymentPlanOption $option): int
    {
        return PaymentPlan::query()
            ->where('plan_type', $option->code)
            ->whereHas('feeAssessment.enrollment', function ($query) use ($option): void {
                $query->where('academic_year_id', $option->academic_year_id);

                if ($option->grade_level_id !== null) {
                    $query->where('grade_level_id', $option->grade_level_id);
                }
            })
            ->count();
    }

    public function setActive(PaymentPlanOption $option, bool $isActive, string $reason, User $actor): PaymentPlanOption
    {
        return DB::transaction(function () use ($option, $isActive, $reason, $actor): PaymentPlanOption {
            $locked = PaymentPlanOption::query()->lockForUpdate()->findOrFail($option->id);
            $locked->update(['is_active' => $isActive]);
            $this->audit->record(
                $actor,
                $isActive ? 'billing.payment_plan_option.activated' : 'billing.payment_plan_option.deactivated',
                $locked,
                ['reason' => $reason],
            );

            return $locked;
        });
    }
}
