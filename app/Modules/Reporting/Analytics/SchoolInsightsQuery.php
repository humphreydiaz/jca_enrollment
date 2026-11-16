<?php

namespace App\Modules\Reporting\Analytics;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Billing\Calculations\Money;
use App\Modules\Reporting\Reports\ReportCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * D-065: owner-facing comparison of one school year against the previous one (pre-final panel:
 * "mataas ang enrollment, mataas ba ang kita compared last year last month, collected and uncollected").
 * Read-only aggregates over enrollment and billing records; no learner-level data leaves this class.
 */
class SchoolInsightsQuery
{
    /** Statuses counted as enrolled, matching the Enrolment Data summary. */
    private const ENROLLED = ['approved', 'enrolled'];

    public function __construct(private readonly ReportCatalog $reports) {}

    /** @return array<string, mixed> */
    public function for(AcademicYear $year): array
    {
        $previous = AcademicYear::query()->where('starts_on', '<', $year->starts_on)->orderByDesc('starts_on')->first();
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();

        $current = $this->finance($year);
        $last = $previous ? $this->finance($previous) : null;
        $enrolled = $this->enrolledCount($year);
        $enrolledLast = $previous ? $this->enrolledCount($previous) : null;

        $months = $this->schoolMonths($year);
        $monthly = $this->monthlyCollections($year, $months, 0);
        $monthlyLast = $previous ? $this->monthlyCollections($previous, $months, -1) : array_fill_keys(array_keys($months), 0.0);
        $installments = $this->installmentsDue($year, $months);

        $thisMonth = $this->collectedBetween($today->startOfMonth(), $today->endOfMonth());
        $lastMonth = $this->collectedBetween($today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth());

        return [
            'year' => $year,
            'previous' => $previous,
            'enrolled' => $enrolled,
            'enrolled_last' => $enrolledLast,
            'finance' => $current,
            'finance_last' => $last,
            'months' => $months,
            'monthly' => $monthly,
            'monthly_last' => $monthlyLast,
            'installments' => $installments,
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'today' => $today,
            'projection' => $this->projection($year, $current, $installments, $months, $monthly, $today),
            'by_grade' => $this->enrolledByGrade($year, $previous),
            'demographics' => $this->reports->enrollmentSummary(['school_year' => $year->public_id]),
            'by_category' => $this->enrolledByCategory($year),
        ];
    }

    private function enrolledCount(AcademicYear $year): int
    {
        return DB::table('enrollments')->where('academic_year_id', $year->id)->whereIn('status', self::ENROLLED)->count();
    }

    /**
     * Assessed, collected and uncollected for the year's finalized assessments, using the same
     * arithmetic as BalanceCalculator, aggregated in one grouped query.
     *
     * @return array{assessments: int, assessed: float, collected: float, uncollected: float, credit: float, rate: float|null}
     */
    public function finance(AcademicYear $year): array
    {
        $sum = fn (string $table, string $column, string $alias, callable $where) => DB::table($table)->selectRaw("fee_assessment_id, SUM({$column}) as {$alias}")->where($where)->groupBy('fee_assessment_id');

        $posted = DB::table('payments')->selectRaw('fee_assessment_id, SUM(amount) as posted')->where('status', 'posted')->groupBy('fee_assessment_id');
        $reversed = DB::table('payment_reversals as pr')->join('payments as p', 'p.id', '=', 'pr.payment_id')->selectRaw('p.fee_assessment_id, SUM(pr.amount) as reversed')->where('pr.status', 'approved')->where('p.status', 'posted')->groupBy('p.fee_assessment_id');
        $debits = $sum('financial_adjustments', 'amount', 'debits', fn ($q) => $q->where('status', 'approved')->where('adjustment_type', 'debit'));
        $reductions = $sum('financial_adjustments', 'amount', 'reductions', fn ($q) => $q->where('status', 'approved')->whereIn('adjustment_type', ['credit', 'waiver']));
        $refunds = $sum('refunds', 'amount', 'refunds', fn ($q) => $q->where('status', 'approved'));
        $outgoing = DB::table('credit_allocations as ca')->join('payments as p', 'p.id', '=', 'ca.source_payment_id')->selectRaw('p.fee_assessment_id, SUM(ca.amount) as outgoing')->groupBy('p.fee_assessment_id');
        $incoming = DB::table('credit_allocations as ca')->join('assessment_items as ai', 'ai.id', '=', 'ca.target_assessment_item_id')->selectRaw('ai.fee_assessment_id, SUM(ca.amount) as incoming')->groupBy('ai.fee_assessment_id');

        $rows = DB::table('fee_assessments as fa')
            ->join('enrollments as e', 'e.id', '=', 'fa.enrollment_id')
            ->where('e.academic_year_id', $year->id)
            ->where('fa.status', 'finalized')
            ->leftJoinSub($posted, 'pay', 'pay.fee_assessment_id', '=', 'fa.id')
            ->leftJoinSub($reversed, 'rev', 'rev.fee_assessment_id', '=', 'fa.id')
            ->leftJoinSub($debits, 'deb', 'deb.fee_assessment_id', '=', 'fa.id')
            ->leftJoinSub($reductions, 'red', 'red.fee_assessment_id', '=', 'fa.id')
            ->leftJoinSub($refunds, 'ref', 'ref.fee_assessment_id', '=', 'fa.id')
            ->leftJoinSub($outgoing, 'cout', 'cout.fee_assessment_id', '=', 'fa.id')
            ->leftJoinSub($incoming, 'cin', 'cin.fee_assessment_id', '=', 'fa.id')
            ->get(['fa.net_total', 'pay.posted', 'rev.reversed', 'deb.debits', 'red.reductions', 'ref.refunds', 'cout.outgoing', 'cin.incoming']);

        $assessed = $collected = $due = $credit = 0;
        foreach ($rows as $row) {
            $c = fn ($value) => Money::cents((string) ($value ?? '0'));
            $charges = $c($row->net_total) + $c($row->debits) - $c($row->reductions);
            $paid = $c($row->posted) - $c($row->reversed) - $c($row->refunds) - $c($row->outgoing) + $c($row->incoming);
            $raw = $charges - $paid;
            $assessed += $charges;
            $collected += $paid;
            $due += max(0, $raw);
            $credit += max(0, -$raw);
        }

        return [
            'assessments' => $rows->count(),
            'assessed' => $assessed / 100,
            'collected' => $collected / 100,
            'uncollected' => $due / 100,
            'credit' => $credit / 100,
            'rate' => $assessed > 0 ? round($collected / $assessed * 100, 1) : null,
        ];
    }

    /**
     * Calendar months of the school year, from the enrollment opening (or start) to the end.
     *
     * @return array<string, string> "Y-m" => "Jun 2026"
     */
    private function schoolMonths(AcademicYear $year): array
    {
        $first = CarbonImmutable::parse(collect([$year->enrollment_opens_on, $year->starts_on])->filter()->min())->startOfMonth();
        $last = CarbonImmutable::parse($year->ends_on ?? $first->addMonths(11))->startOfMonth();
        $months = [];
        for ($month = $first; $month->lte($last) && count($months) < 15; $month = $month->addMonth()) {
            $months[$month->format('Y-m')] = $month->format('M Y');
        }

        return $months;
    }

    /**
     * Posted collections per calendar month for the year's assessments; $shiftYears = -1 lines last
     * year's same months up against this year's.
     *
     * @param  array<string, string>  $months
     * @return array<string, float>
     */
    private function monthlyCollections(AcademicYear $year, array $months, int $shiftYears): array
    {
        $totals = DB::table('payments as p')
            ->join('fee_assessments as fa', 'fa.id', '=', 'p.fee_assessment_id')
            ->join('enrollments as e', 'e.id', '=', 'fa.enrollment_id')
            ->where('e.academic_year_id', $year->id)
            ->where('p.status', 'posted')
            ->selectRaw("DATE_FORMAT(p.received_on, '%Y-%m') as ym, SUM(p.amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $result = [];
        foreach (array_keys($months) as $key) {
            $source = CarbonImmutable::createFromFormat('Y-m-d', $key.'-01')->addYears($shiftYears)->format('Y-m');
            $result[$key] = (float) ($totals[$source] ?? 0);
        }

        return $result;
    }

    /**
     * What the year's payment plans schedule per month (the receivable the school planned for).
     *
     * @param  array<string, string>  $months
     * @return array<string, float>
     */
    private function installmentsDue(AcademicYear $year, array $months): array
    {
        $totals = DB::table('payment_schedule_items as psi')
            ->join('payment_plans as pp', 'pp.id', '=', 'psi.payment_plan_id')
            ->join('fee_assessments as fa', 'fa.id', '=', 'pp.fee_assessment_id')
            ->join('enrollments as e', 'e.id', '=', 'fa.enrollment_id')
            ->where('e.academic_year_id', $year->id)
            ->where('fa.status', 'finalized')
            ->selectRaw("DATE_FORMAT(psi.due_on, '%Y-%m') as ym, SUM(psi.amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        return collect(array_keys($months))->mapWithKeys(fn ($key) => [$key => (float) ($totals[$key] ?? 0)])->all();
    }

    private function collectedBetween(CarbonImmutable $from, CarbonImmutable $to): float
    {
        return (float) DB::table('payments')->where('status', 'posted')
            ->whereBetween('received_on', [$from->toDateString(), $to->toDateString()])->sum('amount');
    }

    /**
     * Two plain estimates for the end of the school year, both capped at what was assessed:
     * "on schedule" adds every installment still to fall due; "current pace" repeats the
     * average of the last three months for the months left.
     *
     * @param  array<string, mixed>  $finance
     * @param  array<string, float>  $installments
     * @param  array<string, string>  $months
     * @param  array<string, float>  $monthly
     * @return array<string, float|int|null>
     */
    private function projection(AcademicYear $year, array $finance, array $installments, array $months, array $monthly, CarbonImmutable $today): array
    {
        $currentKey = $today->format('Y-m');
        $remainingKeys = array_values(array_filter(array_keys($months), fn ($key) => $key > $currentKey));
        $pastKeys = array_values(array_filter(array_keys($months), fn ($key) => $key < $currentKey));
        $recent = array_slice($pastKeys, -3);
        $pace = $recent === [] ? 0.0 : array_sum(array_map(fn ($key) => $monthly[$key] ?? 0, $recent)) / count($recent);

        $stillScheduled = array_sum(array_map(fn ($key) => $installments[$key] ?? 0, array_merge([$currentKey], $remainingKeys)));
        $cap = $finance['assessed'];
        $onSchedule = min($cap, $finance['collected'] + $stillScheduled);
        $atPace = min($cap, $finance['collected'] + $pace * count($remainingKeys));

        return [
            'months_left' => count($remainingKeys),
            'monthly_pace' => round($pace, 2),
            'on_schedule' => round($onSchedule, 2),
            'at_pace' => round($atPace, 2),
            'gap_at_pace' => round(max(0, $cap - $atPace), 2),
            'year_over' => $year->ends_on !== null && $today->gt(CarbonImmutable::parse($year->ends_on)),
        ];
    }

    /** @return Collection<int, array{grade: string, current: int, previous: int}> */
    private function enrolledByGrade(AcademicYear $year, ?AcademicYear $previous): Collection
    {
        $counts = fn (AcademicYear $y) => DB::table('enrollments as e')->join('grade_levels as g', 'g.id', '=', 'e.grade_level_id')
            ->where('e.academic_year_id', $y->id)->whereIn('e.status', self::ENROLLED)
            ->groupBy('g.id')->selectRaw('g.id, COUNT(*) as total')->pluck('total', 'g.id');
        $now = $counts($year);
        $before = $previous ? $counts($previous) : collect();

        return DB::table('grade_levels')->where('is_active', true)->orderBy('sequence')->get(['id', 'name'])
            ->map(fn ($grade) => ['grade' => $grade->name, 'current' => (int) ($now[$grade->id] ?? 0), 'previous' => (int) ($before[$grade->id] ?? 0)])
            ->filter(fn (array $row) => $row['current'] > 0 || $row['previous'] > 0)
            ->values();
    }

    /** @return array<string, int> */
    private function enrolledByCategory(AcademicYear $year): array
    {
        return DB::table('enrollments')->where('academic_year_id', $year->id)->whereIn('status', self::ENROLLED)
            ->groupBy('student_category')->selectRaw('student_category, COUNT(*) as total')
            ->pluck('total', 'student_category')->map(fn ($n) => (int) $n)->all();
    }
}
