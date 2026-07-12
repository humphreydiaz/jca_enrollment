<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\DiscountAward;
use App\Modules\Billing\Models\DiscountAwardEvent;
use App\Modules\Billing\Models\DiscountCampaign;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class AwardDiscountAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(DiscountCampaign $campaign, Enrollment $enrollment, ?User $actor = null): ?DiscountAward
    {
        return DB::transaction(function () use ($campaign, $enrollment, $actor) {
            $locked = DiscountCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if (! $locked->is_active || $enrollment->status !== 'approved' || ($locked->starts_on && today()->lt($locked->starts_on)) || ($locked->ends_on && today()->gt($locked->ends_on))) {
                return null;
            }
            $requestedExisting = DiscountAward::query()->where('discount_campaign_id', $locked->id)->where('enrollment_id', $enrollment->id)->first();
            if ($requestedExisting) {
                return $requestedExisting;
            }
            $candidate = Enrollment::query()->where('academic_year_id', $locked->academic_year_id)->where('status', 'approved')
                ->whereDoesntHave('discountAwards', fn ($query) => $query->where('discount_campaign_id', $locked->id))
                ->orderBy('approved_at')->orderBy('id')->lockForUpdate()->first() ?? $enrollment;
            $existing = DiscountAward::query()->where('discount_campaign_id', $locked->id)->where('enrollment_id', $candidate->id)->first();
            if ($existing) {
                return $existing;
            }$rank = (int) DiscountAward::query()->where('discount_campaign_id', $locked->id)->max('rank') + 1;
            $reopened = DiscountAwardEvent::query()->whereHas('award', fn ($query) => $query->where('discount_campaign_id', $locked->id))->where('event_type', 'slot_reopened')->count();
            if ($locked->slot_limit !== null && $rank > $locked->slot_limit + $reopened) {
                return null;
            }$award = DiscountAward::query()->create(['discount_campaign_id' => $locked->id, 'enrollment_id' => $candidate->id, 'rank' => $rank, 'amount' => $locked->value_amount, 'status' => 'awarded', 'awarded_at' => now(), 'awarded_by' => $actor?->id]);
            $this->audit->record($actor, 'billing.discount.awarded', $award, ['rank' => $rank]);

            return $award;
        });
    }
}
