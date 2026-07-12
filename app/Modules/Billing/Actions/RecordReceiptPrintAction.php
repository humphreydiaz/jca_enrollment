<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\ReceiptPrint;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final class RecordReceiptPrintAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(Payment $payment, User $actor): ReceiptPrint
    {
        return DB::transaction(function () use ($payment, $actor): ReceiptPrint {
            Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $number = (int) ReceiptPrint::query()->where('payment_id', $payment->id)->max('print_number') + 1;
            $print = ReceiptPrint::query()->create(['payment_id' => $payment->id, 'print_number' => $number, 'printed_by' => $actor->id, 'printed_at' => now()]);
            $this->audit->record($actor, 'billing.receipt.printed', $payment, ['print_number' => $number]);

            return $print;
        });
    }
}
