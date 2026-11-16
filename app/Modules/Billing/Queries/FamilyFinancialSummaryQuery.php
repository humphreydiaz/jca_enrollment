<?php

namespace App\Modules\Billing\Queries;

use App\Models\User;
use App\Modules\Billing\Calculations\BalanceCalculator;
use App\Modules\Billing\Calculations\Money;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\People\Models\GuardianStudent;
use Illuminate\Support\Collection;

/**
 * D-064: the read-only financial summary a parent/guardian or student sees in their own portal.
 * Scope is the learner's own record (student) or learners linked to the guardian by a verified,
 * current link that allows the financial view. Only finalized assessments are shown, and only
 * facts a family already holds: fee lines, discounts, totals, posted payments, and the due schedule.
 */
class FamilyFinancialSummaryQuery
{
    public function __construct(private readonly BalanceCalculator $balance) {}

    /** @return list<int> */
    public function visibleStudentIds(User $user): array
    {
        $ids = collect();
        if ($user->hasRole('student') && $user->studentProfile !== null) {
            $ids->push($user->studentProfile->id);
        }
        if ($user->hasRole('guardian') && $user->guardianProfile !== null) {
            $ids = $ids->merge(GuardianStudent::query()
                ->where('guardian_id', $user->guardianProfile->id)
                ->where('may_view_financials', true)
                ->whereNotNull('verified_at')
                ->whereNull('ended_at')
                ->pluck('student_id'));
        }

        return $ids->unique()->values()->all();
    }

    /** @return Collection<int, array<string, mixed>> one entry per finalized assessment, newest school year first */
    public function forUser(User $user): Collection
    {
        $studentIds = $this->visibleStudentIds($user);
        if ($studentIds === []) {
            return collect();
        }

        return FeeAssessment::query()
            ->where('status', 'finalized')
            ->whereHas('enrollment', fn ($enrollment) => $enrollment->whereIn('student_id', $studentIds))
            ->with([
                'enrollment.student', 'enrollment.gradeLevel', 'enrollment.academicYear',
                'items', 'plan.scheduleItems',
                'payments' => fn ($payments) => $payments->where('status', 'posted')->orderBy('received_on'),
            ])
            ->get()
            ->sortByDesc(fn (FeeAssessment $assessment) => $assessment->enrollment->academicYear?->starts_on)
            ->map(fn (FeeAssessment $assessment): array => $this->summarise($assessment))
            ->values();
    }

    /** @return array<string, mixed> */
    private function summarise(FeeAssessment $assessment): array
    {
        $balance = $this->balance->calculate($assessment);
        $paidCents = max(0, Money::cents($balance['paid']));
        $today = now('Asia/Manila')->startOfDay();

        // Apply what was paid to the installments in due-date order.
        $remainingPaid = $paidCents;
        $schedule = collect($assessment->plan?->scheduleItems ?? [])->sortBy('due_on')->values()->map(function ($item) use (&$remainingPaid, $today): array {
            $amount = Money::cents((string) $item->amount);
            $covered = min($amount, $remainingPaid);
            $remainingPaid -= $covered;
            $status = match (true) {
                $covered >= $amount => 'paid',
                $item->due_on !== null && $item->due_on->lt($today) => 'overdue',
                $covered > 0 => 'partly_paid',
                default => 'upcoming',
            };

            return [
                'label' => $item->label ?: 'Installment '.$item->installment_number,
                'due_on' => $item->due_on,
                'amount' => Money::decimal($amount),
                'unpaid' => Money::decimal($amount - $covered),
                'status' => $status,
            ];
        });

        $nextDue = $schedule->first(fn (array $row) => $row['status'] !== 'paid');

        return [
            'assessment' => $assessment,
            'learner' => $assessment->enrollment->student->full_name,
            'grade' => $assessment->enrollment->gradeLevel?->name,
            'school_year' => $assessment->enrollment->academicYear?->name,
            'plan' => $assessment->plan?->plan_type,
            'items' => $assessment->items,
            'gross' => (string) $assessment->gross_total,
            'discounts' => (string) $assessment->discount_total,
            'net' => (string) $assessment->net_total,
            'paid' => $balance['paid'],
            'balance' => $balance['due'],
            'credit' => $balance['credit'],
            'schedule' => $schedule,
            'next_due' => $nextDue,
            'overdue' => $schedule->where('status', 'overdue')->sum(fn (array $row) => (float) $row['unpaid']),
            'payments' => $assessment->payments,
        ];
    }
}
