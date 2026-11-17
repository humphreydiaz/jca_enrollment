<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Calculations\Money;
use App\Modules\Billing\Models\AssessmentItem;
use App\Modules\Billing\Models\DiscountAward;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAssessmentAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(Enrollment $enrollment, FeeSchedule $schedule, array $selectedIds, User $actor, ?string $revisionReason = null): FeeAssessment
    {
        // Form values arrive as strings; normalize so the strict in_array match below works.
        $selectedIds = array_map('intval', $selectedIds);

        return DB::transaction(function () use ($enrollment, $schedule, $selectedIds, $actor, $revisionReason) {
            if (! in_array($enrollment->status, ['approved', 'enrolled'], true)) {
                $enrollment = Enrollment::query()
    ->lockForUpdate()
    ->findOrFail($enrollment->id);

$schedule = FeeSchedule::query()
    ->findOrFail($schedule->id);
                throw ValidationException::withMessages(['enrollment' => 'Only approved or enrolled records may be assessed.']);
            }
            if ($schedule->academic_year_id !== $enrollment->academic_year_id || ($schedule->grade_level_id !== null && $schedule->grade_level_id !== $enrollment->grade_level_id)) {
                throw ValidationException::withMessages(['fee_schedule_id' => 'The fee schedule does not match the enrollment.']);
            }
            if ($schedule->status !== 'published') {
                throw ValidationException::withMessages(['fee_schedule_id' => 'Only a published fee schedule may be assessed.']);
            }
            $existingDraft = FeeAssessment::query()->where('enrollment_id', $enrollment->id)->where('status', 'draft')->lockForUpdate()->first();
            if ($existingDraft) {
                if ($existingDraft->fee_schedule_id === $schedule->id) {
                    return $existingDraft;
                }

                throw ValidationException::withMessages(['assessment' => 'This enrollment already has a draft assessment. Edit or cancel it before creating another.']);
            }
            $previous = FeeAssessment::query()->where('enrollment_id', $enrollment->id)->where('status', 'finalized')->lockForUpdate()->latest('revision_number')->first();
           $hasPostedPayment = FeeAssessment::query()
    ->where('enrollment_id', $enrollment->id)
    ->whereHas('payments', function ($query) {
        $query->where('status', 'posted');
    })
    ->exists();

if ($hasPostedPayment) {
                throw ValidationException::withMessages(['assessment' => 'A posted payment exists; use an approved adjustment instead of replacing the assessment.']);
            }
            $revision = (int) FeeAssessment::query()->where('enrollment_id', $enrollment->id)->max('revision_number') + 1;
            $assessment = FeeAssessment::query()->create(['assessment_reference' => 'ASM-'.$enrollment->academic_year_id.'-'.$enrollment->id.'-'.$revision, 'enrollment_id' => $enrollment->id, 'fee_schedule_id' => $schedule->id, 'previous_assessment_id' => $previous?->id, 'revision_number' => $revision, 'revision_reason' => $revisionReason, 'created_by' => $actor->id]);
            $gross = [];
            foreach ($schedule->items()->with('feeType')->get() as $item) {
                if (! $item->is_required && ! in_array($item->id, $selectedIds, true)) {
                    continue;
                }$amount = (string) $item->amount;
                AssessmentItem::query()->create(['fee_assessment_id' => $assessment->id, 'fee_type_id' => $item->fee_type_id, 'fee_code' => $item->feeType->code, 'fee_name' => $item->feeType->name, 'category' => $item->feeType->category, 'quantity' => 1, 'unit_amount' => $amount, 'gross_amount' => $amount, 'net_amount' => $amount, 'is_permit_critical' => $item->feeType->is_permit_critical]);
                $gross[] = $amount;
            }
            $grossTotal = Money::sum($gross);
            $award = DiscountAward::query()->where('enrollment_id', $enrollment->id)->where('status', 'awarded')->lockForUpdate()->first();
            $discount = '0.00';
            if ($award) {
                $tuition = $assessment->items()->where('fee_code', 'TUI')->lockForUpdate()->first();
                if ($tuition) {
                    $discountCents = min(Money::cents((string) $award->amount), Money::cents((string) $tuition->gross_amount));
                    $discount = Money::decimal($discountCents);
                    $tuition->update(['discount_amount' => $discount, 'net_amount' => Money::decimal(Money::cents((string) $tuition->gross_amount) - $discountCents)]);
                    if ($award->fee_assessment_id === null) {
                        $award->update(['fee_assessment_id' => $assessment->id, 'amount' => $discount]);
                    }
                }
            }
            $assessment->update(['gross_total' => $grossTotal, 'discount_total' => $discount, 'net_total' => Money::decimal(Money::cents($grossTotal) - Money::cents($discount))]);
            $this->audit->record($actor, 'billing.assessment.created', $assessment);

            return $assessment;
        });
    }
}
