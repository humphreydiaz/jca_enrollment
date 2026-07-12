<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\Payment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateDraftPaymentAction
{
    public function __construct(private AllocateDraftPaymentAction $allocate, private AuditRecorder $audit) {}

    public function execute(Payment $payment, array $data, User $actor): Payment
    {
        return DB::transaction(function () use ($payment, $data, $actor): Payment {
            $record = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($record->status !== 'draft') {
                throw ValidationException::withMessages(['payment' => 'Only a draft payment may be edited.']);
            }
            $preferred = $data['assessment_item_id'] ?? null;
            $record->update(collect($data)->except('assessment_item_id')->all());
            $this->allocate->execute($record, $preferred);
            $this->audit->record($actor, 'billing.payment.draft_updated', $record, ['method' => $record->method]);

            return $record->load('allocations');
        });
    }
}
