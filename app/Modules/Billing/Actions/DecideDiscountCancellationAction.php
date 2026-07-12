<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\DiscountAward;
use App\Modules\Billing\Models\DiscountAwardEvent;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DecideDiscountCancellationAction
{
    public function __construct(private AuditRecorder $audit, private AwardDiscountAction $award) {}

    public function request(DiscountAward $award, string $reason, User $accounting): DiscountAwardEvent
    {
        $award = DiscountAward::query()->findOrFail($award->id);
        if (! $accounting->hasRole('accounting') || $award->status !== 'awarded') {
            throw ValidationException::withMessages(['discount' => 'Accounting may request cancellation only for an active award.']);
        }
        $event = DiscountAwardEvent::query()->create(['discount_award_id' => $award->id, 'event_type' => 'cancellation_requested', 'reason' => $reason, 'actor_user_id' => $accounting->id, 'occurred_at' => now()]);
        $this->audit->record($accounting, 'billing.discount_cancellation.requested', $award);

        return $event;
    }

    public function decide(DiscountAward $award, bool $approve, string $reason, User $admin): DiscountAward
    {
        return DB::transaction(function () use ($award, $approve, $reason, $admin): DiscountAward {
            if (! $admin->hasRole('admin') || ! $award->events()->where('event_type', 'cancellation_requested')->exists()) {
                throw ValidationException::withMessages(['discount' => 'Admin may decide only a requested cancellation.']);
            }
            $record = DiscountAward::query()->lockForUpdate()->findOrFail($award->id);
            if ($record->status !== 'awarded') {
                throw ValidationException::withMessages(['discount' => 'This award is no longer active.']);
            }
            if ($approve) {
                $record->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $admin->id, 'cancellation_reason' => $reason]);
            }
            DiscountAwardEvent::query()->create(['discount_award_id' => $record->id, 'event_type' => $approve ? 'cancelled' : 'cancellation_rejected', 'reason' => $reason, 'actor_user_id' => $admin->id, 'occurred_at' => now()]);
            $this->audit->record($admin, $approve ? 'billing.discount.cancelled' : 'billing.discount_cancellation.rejected', $record);

            return $record;
        });
    }

    public function reopenSlot(DiscountAward $cancelledAward, string $reason, User $admin): ?DiscountAward
    {
        if (! $admin->hasRole('admin') || $cancelledAward->status !== 'cancelled') {
            throw ValidationException::withMessages(['discount' => 'Only Admin may reopen a cancelled award slot.']);
        }
        if ($cancelledAward->events()->where('event_type', 'slot_reopened')->exists()) {
            throw ValidationException::withMessages(['discount' => 'This cancelled slot has already been reopened.']);
        }
        $event = DiscountAwardEvent::query()->create(['discount_award_id' => $cancelledAward->id, 'event_type' => 'slot_reopened', 'reason' => $reason, 'actor_user_id' => $admin->id, 'occurred_at' => now()]);
        $this->audit->record($admin, 'billing.discount_slot.reopened', $cancelledAward);

        $campaign = $cancelledAward->campaign;
        $next = Enrollment::query()->where('academic_year_id', $campaign->academic_year_id)->where('status', 'approved')
            ->whereDoesntHave('discountAwards', fn ($query) => $query->where('discount_campaign_id', $campaign->id))
            ->orderBy('approved_at')->orderBy('id')->first();

        return $next ? $this->award->execute($campaign, $next, $admin) : null;
    }
}
