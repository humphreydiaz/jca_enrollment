<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\SiblingDiscountConfirmation;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** D-067: withdrawing a confirmation keeps the row (who, when, why); it never deletes history. */
class WithdrawSiblingDiscountAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(SiblingDiscountConfirmation $confirmation, string $reason, User $actor): SiblingDiscountConfirmation
    {
        abort_unless($actor->hasAnyRole(['admin', 'registrar']), 403);

        return DB::transaction(function () use ($confirmation, $reason, $actor): SiblingDiscountConfirmation {
            $locked = SiblingDiscountConfirmation::query()->lockForUpdate()->findOrFail($confirmation->id);
            if ($locked->withdrawn_at !== null) {
                throw ValidationException::withMessages(['sibling_discount' => 'This confirmation was already withdrawn.']);
            }
            $locked->update(['active_key' => null, 'withdrawn_by' => $actor->id, 'withdrawn_at' => now(), 'withdraw_reason' => trim($reason)]);
            $this->audit->record($actor, 'billing.sibling_discount.withdrawn', $locked, ['reason' => trim($reason)]);

            return $locked;
        });
    }
}
