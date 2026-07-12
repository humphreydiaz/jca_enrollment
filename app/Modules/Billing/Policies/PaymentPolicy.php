<?php

namespace App\Modules\Billing\Policies;

use App\Models\User;
use App\Modules\Billing\Models\Payment;

class PaymentPolicy
{
    public function view(User $u, Payment $p): bool
    {
        if ($u->hasAnyRole(['admin', 'accounting'])) {
            return true;
        }

        return $p->status === 'posted'
            && ! $u->hasAnyRole(['registrar', 'teacher'])
            && $u->can('view', $p->assessment);
    }

    public function manage(User $u): bool
    {
        return $u->hasRole('accounting');
    }

    public function approve(User $u): bool
    {
        return $u->hasRole('admin');
    }

    public function print(User $u, Payment $payment): bool
    {
        return $payment->status === 'posted' && $this->view($u, $payment);
    }
}
