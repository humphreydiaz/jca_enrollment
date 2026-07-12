<?php

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Actions\AwardDiscountAction;
use App\Modules\Billing\Models\DiscountCampaign;
use App\Modules\Enrollment\Events\EnrollmentApproved;

final class AwardApprovedEnrollmentDiscount
{
    public function __construct(private AwardDiscountAction $award) {}

    public function handle(EnrollmentApproved $event): void
    {
        $campaign = DiscountCampaign::query()
            ->where('academic_year_id', $event->enrollment->academic_year_id)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_on')->orWhereDate('starts_on', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
            ->orderBy('id')->first();
        if ($campaign) {
            $this->award->execute($campaign, $event->enrollment, $event->actor);
        }
    }
}
