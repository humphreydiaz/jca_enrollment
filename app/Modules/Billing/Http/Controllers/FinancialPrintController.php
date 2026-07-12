<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\RecordPermitPrintAction;
use App\Modules\Billing\Actions\RecordReceiptPrintAction;
use App\Modules\Billing\Calculations\BalanceCalculator;
use App\Modules\Billing\Models\ExamPermit;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\FinancialPrintSetting;
use App\Modules\Billing\Models\Payment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FinancialPrintController
{
    public function statement(FeeAssessment $assessment, Request $request, BalanceCalculator $balance, AuditRecorder $audit): View
    {
        if ($request->user()->hasAnyRole(['guardian', 'student']) && ! Gate::allows('view', $assessment)) {
            abort(404);
        }
        Gate::authorize('view', $assessment);
        if ($request->user()->hasRole('registrar') && ! $request->user()->hasAnyRole(['admin', 'accounting'])) {
            abort(403);
        }
        $assessment->load(['enrollment.student', 'enrollment.gradeLevel', 'enrollment.academicYear', 'enrollment.currentSectionAssignment.section', 'items', 'plan.scheduleItems', 'payments' => fn ($query) => $query->where('status', 'posted')]);
        $audit->record($request->user(), 'billing.statement.printed', $assessment);

        return view('billing.print.statement', ['assessment' => $assessment, 'balance' => $balance->calculate($assessment), 'logoData' => $this->logoData()]);
    }

    public function receipt(Payment $payment, Request $request, RecordReceiptPrintAction $record): View
    {
        if ($request->user()->hasAnyRole(['guardian', 'student']) && ! Gate::allows('print', $payment)) {
            abort(404);
        }
        Gate::authorize('print', $payment);
        $print = $record->execute($payment, $request->user());

        return view('billing.print.receipt', ['payment' => $payment->load(['assessment.enrollment.student', 'allocations', 'poster']), 'print' => $print, 'logoData' => $this->logoData()]);
    }

    public function permit(ExamPermit $permit, Request $request, RecordPermitPrintAction $record): View
    {
        if ($request->user()->hasAnyRole(['guardian', 'student']) && ! Gate::allows('print', $permit)) {
            abort(404);
        }
        Gate::authorize('print', $permit);
        $print = $record->execute($permit, $request->user());

        $permit->load(['enrollment.student', 'enrollment.gradeLevel', 'enrollment.academicYear', 'enrollment.currentSectionAssignment.section', 'period', 'issuer']);
        $signatoryRole = FinancialPrintSetting::query()->where('academic_year_id', $permit->enrollment->academic_year_id)->where('setting_key', 'permit_signatory_role')->value('setting_value') ?: 'Authorized School Signatory';

        return view('billing.print.permit', ['permit' => $permit, 'print' => $print, 'logoData' => $this->logoData(), 'signatoryRole' => $signatoryRole]);
    }

    public function ledger(FeeAssessment $assessment, Request $request, BalanceCalculator $balance, AuditRecorder $audit): View
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'accounting']), 403);
        $assessment->load(['enrollment.student', 'items', 'discountAwards', 'payments.allocations', 'payments.reversals', 'adjustments']);
        $audit->record($request->user(), 'billing.ledger.printed', $assessment);

        return view('billing.print.ledger', ['assessment' => $assessment, 'balance' => $balance->calculate($assessment)]);
    }

    private function logoData(): ?string
    {
        $path = base_path('JCA Logo.jpg');

        return is_file($path) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($path)) : null;
    }
}
