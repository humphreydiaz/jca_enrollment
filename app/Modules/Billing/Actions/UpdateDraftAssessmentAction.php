<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Calculations\Money;
use App\Modules\Billing\Models\AssessmentItem;
use App\Modules\Billing\Models\DiscountAward;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateDraftAssessmentAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(FeeAssessment $assessment, FeeSchedule $schedule, array $selectedIds, ?string $reason, User $actor): FeeAssessment
    {
        // Form values arrive as strings; normalize so the strict in_array match below works.
        $selectedIds = array_map('intval', $selectedIds);

        return DB::transaction(function () use ($assessment, $schedule, $selectedIds, $reason, $actor): FeeAssessment {
            $record = FeeAssessment::query()->with('enrollment')->lockForUpdate()->findOrFail($assessment->id);
            if ($record->status !== 'draft') {
                throw ValidationException::withMessages(['assessment' => 'Only a draft assessment may be edited.']);
            }
            if ($schedule->status !== 'published' || $schedule->academic_year_id !== $record->enrollment->academic_year_id || ($schedule->grade_level_id && $schedule->grade_level_id !== $record->enrollment->grade_level_id)) {
                throw ValidationException::withMessages(['fee_schedule_id' => 'Select a published schedule for the same school year and grade.']);
            }
            $record->items()->delete();
            $gross = [];
            foreach ($schedule->items()->with('feeType')->get() as $item) {
                if (! $item->is_required && ! in_array($item->id, $selectedIds, true)) {
                    continue;
                }
                $amount = (string) $item->amount;
                AssessmentItem::query()->create(['fee_assessment_id' => $record->id, 'fee_type_id' => $item->fee_type_id, 'fee_code' => $item->feeType->code, 'fee_name' => $item->feeType->name, 'category' => $item->feeType->category, 'quantity' => 1, 'unit_amount' => $amount, 'gross_amount' => $amount, 'net_amount' => $amount, 'is_permit_critical' => $item->feeType->is_permit_critical]);
                $gross[] = $amount;
            }
            $grossTotal = Money::sum($gross);
            $discountCents = 0;
            $award = DiscountAward::query()->where('enrollment_id', $record->enrollment_id)->where('status', 'awarded')->first();
            $tuition = $record->items()->where('fee_code', 'TUI')->first();
            if ($award && $tuition) {
                $discountCents = min(Money::cents((string) $award->amount), Money::cents((string) $tuition->gross_amount));
                $tuition->update(['discount_amount' => Money::decimal($discountCents), 'net_amount' => Money::decimal(Money::cents((string) $tuition->gross_amount) - $discountCents)]);
            }
            $record->update(['fee_schedule_id' => $schedule->id, 'gross_total' => $grossTotal, 'discount_total' => Money::decimal($discountCents), 'net_total' => Money::decimal(Money::cents($grossTotal) - $discountCents), 'revision_reason' => $reason]);
            $this->audit->record($actor, 'billing.assessment.draft_updated', $record);

            return $record->load('items');
        });
    }
}
