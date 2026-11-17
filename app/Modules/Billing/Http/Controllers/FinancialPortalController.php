<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Calculations\BalanceCalculator;
use App\Modules\Billing\Calculations\PermitEligibilityCalculator;
use App\Modules\Billing\Models\DiscountAward;
use App\Modules\Billing\Models\ExamPeriod;
use App\Modules\Billing\Models\ExamPermit;
use App\Modules\Billing\Models\ExamPermitOverride;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\PaymentPlanOption;
use App\Modules\Billing\Models\SiblingDiscountConfirmation;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FinancialPortalController
{
    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless(
            $user->hasAnyRole(['admin', 'accounting', 'registrar', 'guardian', 'student']),
            403
        );

        $query = FeeAssessment::query()
            ->where('status', 'finalized')
            ->with([
                'enrollment.student',
                'enrollment.gradeLevel',
                'enrollment.academicYear',
            ]);

        if (! $user->hasAnyRole(['admin', 'accounting', 'registrar'])) {
            if ($user->hasRole('student')) {
                $query->whereHas(
                    'enrollment',
                    fn ($enrollment) => $enrollment->where(
                        'student_id',
                        $user->studentProfile?->id ?? 0
                    )
                );
            } elseif ($user->hasRole('guardian')) {
                $guardianId = $user->guardianProfile?->id ?? 0;

                $query->whereHas(
                    'enrollment.student.guardianLinks',
                    fn ($link) => $link
                        ->where('guardian_id', $guardianId)
                        ->where('may_view_financials', true)
                        ->whereNotNull('verified_at')
                        ->whereNull('ended_at')
                );
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return view('billing.portal.index', [
            'assessments' => $query->latest('finalized_at')->get(),
        ]);
    }

    public function show(
        FeeAssessment $assessment,
        Request $request,
        BalanceCalculator $balance,
        AuditRecorder $audit,
        PermitEligibilityCalculator $permitEligibility
    ): View {
        $user = $request->user();

        if (
            $user->hasAnyRole(['guardian', 'student']) &&
            ! Gate::allows('view', $assessment)
        ) {
            abort(404);
        }

        Gate::authorize('view', $assessment);

        $assessment->load([
            'enrollment.student',
            'enrollment.gradeLevel',
            'enrollment.academicYear',
            'items',
            'plan.scheduleItems',
            'payments' => fn ($query) => $query->where('status', 'posted'),
        ]);

        $issuedPermit = ExamPermit::query()
            ->where('enrollment_id', $assessment->enrollment_id)
            ->where('status', 'issued')
            ->latest('issued_at')
            ->first();

        $permitStatus = $issuedPermit?->status;

        $discountAwards = DiscountAward::query()
            ->where('enrollment_id', $assessment->enrollment_id)
            ->with('events')
            ->get();

        $audit->record(
            $user,
            'billing.financial_summary.viewed',
            $assessment
        );

        if (
            $user->hasRole('registrar') &&
            ! $user->hasAnyRole(['admin', 'accounting'])
        ) {
            return view('billing.registrar.summary', [
                'assessment' => $assessment,
                'balance' => $balance->calculate($assessment),
                'permitStatus' => $permitStatus,
                'issuedPermit' => $issuedPermit,
            ]);
        }

        $availablePlanOptions = collect();

        if ($user->hasRole('accounting')) {
            $configuredOptions = PaymentPlanOption::query()
                ->where(
                    'academic_year_id',
                    $assessment->enrollment->academic_year_id
                )
                ->where(
                    fn ($query) => $query
                        ->whereNull('grade_level_id')
                        ->orWhere(
                            'grade_level_id',
                            $assessment->enrollment->grade_level_id
                        )
                )
                ->orderByRaw('grade_level_id IS NULL')
                ->get();

            $availablePlanOptions = $configuredOptions->isEmpty()
                ? collect([
                    ['code' => 'full', 'name' => 'Full Payment'],
                    ['code' => 'semiannual', 'name' => 'Semiannual'],
                    ['code' => 'quarterly', 'name' => 'Quarterly'],
                    ['code' => 'monthly', 'name' => 'Monthly'],
                ])
                : $configuredOptions
                    ->where('is_active', true)
                    ->unique('code')
                    ->values()
                    ->map(fn ($option) => [
                        'code' => $option->code,
                        'name' => $option->name,
                    ]);
        }

        $examPeriods = collect();
        $examPermitEligibility = collect();

        if ($user->hasRole('accounting')) {
            $examPeriods = ExamPeriod::query()
                ->where(
                    'academic_year_id',
                    $assessment->enrollment->academic_year_id
                )
                ->orderBy('starts_on')
                ->get();

            $overrides = ExamPermitOverride::query()
                ->where('enrollment_id', $assessment->enrollment_id)
                ->whereIn('status', ['requested', 'approved'])
                ->get();

            $issuedPeriodIds = ExamPermit::query()
                ->where('enrollment_id', $assessment->enrollment_id)
                ->where('status', 'issued')
                ->pluck('exam_period_id')
                ->all();

            foreach ($examPeriods as $period) {
                $result = $permitEligibility->calculate(
                    $assessment,
                    $period
                );

                $activeOverride = $overrides->first(function ($override) use ($period) {
                    return $override->status === 'approved'
                        && (
                            $override->exam_period_id === null ||
                            (int) $override->exam_period_id === (int) $period->id
                        )
                        && $override->effective_from
                        && today()->gte($override->effective_from)
                        && (
                            ! $override->expires_on ||
                            today()->lte($override->expires_on)
                        );
                });

                $pendingOverride = $overrides->first(function ($override) use ($period) {
                    return $override->status === 'requested'
                        && (
                            $override->exam_period_id === null ||
                            (int) $override->exam_period_id === (int) $period->id
                        );
                });

                $hasIssuedPermit = in_array(
                    $period->id,
                    $issuedPeriodIds
                );

                $examPermitEligibility->put($period->id, [
                    'required' => $result['required'],
                    'satisfied' => $result['satisfied'],
                    'eligible' => $result['eligible'],
                    'has_issued_permit' => $hasIssuedPermit,
                    'has_approved_override' => $activeOverride !== null,
                    'has_pending_override' => $pendingOverride !== null,
                    'can_request_override' => ! $result['eligible']
                        && ! $hasIssuedPermit
                        && $activeOverride === null
                        && $pendingOverride === null,
                ]);
            }
        }

        $availableSchedules = collect();

        if ($user->hasRole('accounting')) {
            $availableSchedules = FeeSchedule::query()
                ->where('status', 'published')
                ->where(
                    'academic_year_id',
                    $assessment->enrollment->academic_year_id
                )
                ->where(
                    fn ($query) => $query
                        ->whereNull('grade_level_id')
                        ->orWhere(
                            'grade_level_id',
                            $assessment->enrollment->grade_level_id
                        )
                )
                ->with('items.feeType')
                ->get();
        }

        return view('billing.portal.show', [
            'assessment' => $assessment,
            'balance' => $balance->calculate($assessment),
            'permitStatus' => $permitStatus,
            'issuedPermit' => $issuedPermit,
            'discountAwards' => $discountAwards,
            'siblingDiscount' => SiblingDiscountConfirmation::query()->active()->with('confirmer')->where('enrollment_id', $assessment->enrollment_id)->first(),
            'availablePlanOptions' => $availablePlanOptions,
            'examPeriods' => $examPeriods,
            'examPermitEligibility' => $examPermitEligibility,
            'availableSchedules' => $availableSchedules,
        ]);
    }
}
