<?php

namespace App\Modules\Billing\Policies;

use App\Models\User;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\People\Models\GuardianStudent;

class FeeAssessmentPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->hasAnyRole(['admin', 'accounting', 'registrar']);
    }

    public function view(User $u, FeeAssessment $a): bool
    {
        if ($u->hasAnyRole(['admin', 'accounting', 'registrar'])) {
            return true;
        }if ($u->studentProfile?->id === $a->enrollment->student_id) {
            return true;
        }$guardian = $u->guardianProfile?->id;

        return $guardian !== null && GuardianStudent::query()->where('guardian_id', $guardian)->where('student_id', $a->enrollment->student_id)->where('may_view_financials', true)->whereNotNull('verified_at')->whereNull('ended_at')->exists();
    }

    public function manage(User $u): bool
    {
        return $u->hasRole('accounting');
    }
}
