<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\ExamPermit;
use App\Modules\Billing\Models\ExamPermitPrint;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final class RecordPermitPrintAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(ExamPermit $permit, User $actor): ExamPermitPrint
    {
        return DB::transaction(function () use ($permit, $actor): ExamPermitPrint {
            ExamPermit::query()->lockForUpdate()->findOrFail($permit->id);
            $number = (int) ExamPermitPrint::query()->where('exam_permit_id', $permit->id)->max('print_number') + 1;
            $print = ExamPermitPrint::query()->create(['exam_permit_id' => $permit->id, 'print_number' => $number, 'printed_by' => $actor->id, 'printed_at' => now()]);
            $this->audit->record($actor, 'billing.permit.printed', $permit, ['print_number' => $number]);

            return $print;
        });
    }
}
