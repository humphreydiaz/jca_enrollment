<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\AllocateStudentCreditAction;
use App\Modules\Billing\Actions\ApproveAdjustmentAction;
use App\Modules\Billing\Actions\ApprovePaymentToFollowAction;
use App\Modules\Billing\Actions\ApproveRefundAction;
use App\Modules\Billing\Actions\ApproveReversalAction;
use App\Modules\Billing\Actions\CancelDraftFinancialRecordAction;
use App\Modules\Billing\Actions\CreateAssessmentAction;
use App\Modules\Billing\Actions\CreateDraftPaymentAction;
use App\Modules\Billing\Actions\DecideDiscountCancellationAction;
use App\Modules\Billing\Actions\FinalizeAssessmentAction;
use App\Modules\Billing\Actions\PostPaymentAction;
use App\Modules\Billing\Actions\RequestAdjustmentAction;
use App\Modules\Billing\Actions\RequestRefundAction;
use App\Modules\Billing\Actions\RequestReversalAction;
use App\Modules\Billing\Actions\SelectPaymentPlanAction;
use App\Modules\Billing\Actions\UpdateDraftAssessmentAction;
use App\Modules\Billing\Actions\UpdateDraftPaymentAction;
use App\Modules\Billing\Calculations\BalanceCalculator;
use App\Modules\Billing\Http\Requests\PaymentToFollowRequest;
use App\Modules\Billing\Http\Requests\RequestAdjustmentRequest;
use App\Modules\Billing\Http\Requests\RequestReversalRequest;
use App\Modules\Billing\Http\Requests\SelectPaymentPlanRequest;
use App\Modules\Billing\Http\Requests\StoreAssessmentRequest;
use App\Modules\Billing\Http\Requests\StorePaymentRequest;
use App\Modules\Billing\Models\AssessmentItem;
use App\Modules\Billing\Models\DiscountAward;
use App\Modules\Billing\Models\ExamPermitOverride;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\FinancialAdjustment;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentReversal;
use App\Modules\Billing\Models\Refund;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BillingController
{
    public function index(Request $request, BalanceCalculator $balance): View
    {
        Gate::authorize('viewAny', FeeAssessment::class);
        $assessments = FeeAssessment::query()->with(['enrollment.student', 'items', 'payments'])->latest()->paginate(20);

        return view('billing.accounting.index', [
            'assessments' => $assessments,
            'balances' => $assessments->getCollection()->mapWithKeys(fn ($assessment) => [$assessment->id => $balance->calculate($assessment)]),
            'draftPayments' => $request->user()->hasRole('accounting') ? Payment::query()->where('status', 'draft')->with('assessment.enrollment.student')->get() : collect(),
            'assessableEnrollments' => $request->user()->hasRole('accounting') ? Enrollment::query()->whereIn('status', ['approved', 'enrolled'])->whereDoesntHave('feeAssessments')->with(['student', 'gradeLevel', 'academicYear'])->orderBy('approved_at')->get() : collect(),
            'publishedSchedules' => $request->user()->hasRole('accounting') ? FeeSchedule::query()->where('status', 'published')->with('items.feeType')->get() : collect(),
            'pendingReversals' => $request->user()->hasRole('admin') ? PaymentReversal::query()->where('status', 'requested')->with('payment')->get() : collect(),
            'pendingAdjustments' => $request->user()->hasRole('admin') ? FinancialAdjustment::query()->where('status', 'requested')->get() : collect(),
            'pendingRefunds' => $request->user()->hasRole('admin') ? Refund::query()->where('status', 'requested')->get() : collect(),
            'pendingOverrides' => $request->user()->hasRole('admin') ? ExamPermitOverride::query()->where('status', 'requested')->get() : collect(),
            'pendingDiscountAwards' => $request->user()->hasRole('admin') ? DiscountAward::query()->where('status', 'awarded')->whereHas('events', fn ($query) => $query->where('event_type', 'cancellation_requested'))->with('events')->get() : collect(),
            'cancelledDiscountAwards' => $request->user()->hasRole('admin') ? DiscountAward::query()->where('status', 'cancelled')->whereDoesntHave('events', fn ($query) => $query->where('event_type', 'slot_reopened'))->get() : collect(),
        ]);
    }

    public function createAssessment(Enrollment $enrollment, StoreAssessmentRequest $request, CreateAssessmentAction $create): RedirectResponse
    {
        Gate::authorize('manage', FeeAssessment::class);
        $data = $request->validated();
        $schedule = FeeSchedule::query()->findOrFail($data['fee_schedule_id']);
        $create->execute($enrollment, $schedule, $data['selected_items'] ?? [], $request->user(), $data['revision_reason'] ?? null);

        return back()->with('status', 'Draft assessment created.');
    }

    public function finalize(FeeAssessment $assessment, Request $request, FinalizeAssessmentAction $finalize): RedirectResponse
    {
        Gate::authorize('manage', FeeAssessment::class);
        $finalize->execute($assessment, $request->user());

        return back()->with('status', 'Assessment finalized and frozen.');
    }

    public function updateAssessment(FeeAssessment $assessment, StoreAssessmentRequest $request, UpdateDraftAssessmentAction $update): RedirectResponse
    {
        $schedule = FeeSchedule::query()->findOrFail($request->validated('fee_schedule_id'));
        $update->execute($assessment, $schedule, $request->validated('selected_items', []), $request->validated('revision_reason'), $request->user());

        return back()->with('status', 'Draft assessment updated.');
    }

    public function payment(FeeAssessment $assessment, StorePaymentRequest $request, CreateDraftPaymentAction $create): RedirectResponse
    {
        Gate::authorize('manage', FeeAssessment::class);
        $create->execute($assessment, $request->validated(), $request->user());

        return back()->with('status', 'Draft payment created.');
    }

    public function post(Payment $payment, Request $request, PostPaymentAction $post): RedirectResponse
    {
        Gate::authorize('manage', Payment::class);
        $post->execute($payment, $request->user());

        return back()->with('status', 'Payment posted.');
    }

    public function updatePayment(Payment $payment, StorePaymentRequest $request, UpdateDraftPaymentAction $update): RedirectResponse
    {
        Gate::authorize('manage', Payment::class);
        $update->execute($payment, $request->validated(), $request->user());

        return back()->with('status', 'Draft payment updated and reallocated.');
    }

    public function selectPlan(FeeAssessment $assessment, SelectPaymentPlanRequest $request, SelectPaymentPlanAction $select): RedirectResponse
    {
        $select->execute($assessment, $request->validated('plan_type'), $request->user());

        return back()->with('status', 'Payment plan selected and due schedule generated.');
    }

    public function paymentToFollow(FeeAssessment $assessment, PaymentToFollowRequest $request, ApprovePaymentToFollowAction $approve): RedirectResponse
    {
        $approve->execute($assessment, $request->validated('approved_until'), $request->validated('reason'), $request->user());

        return back()->with('status', 'Payment-to-follow arrangement recorded.');
    }

    public function requestReversal(Payment $payment, RequestReversalRequest $request, RequestReversalAction $create): RedirectResponse
    {
        $create->execute($payment, $request->validated('amount'), $request->validated('reason'), $request->user());

        return back()->with('status', 'Reversal submitted for Admin decision.');
    }

    public function decideReversal(PaymentReversal $reversal, Request $request, ApproveReversalAction $decide): RedirectResponse
    {
        Gate::authorize('approve', Payment::class);
        $approve = $request->boolean('approve');
        $decide->execute($reversal, $request->user(), $approve);

        return back()->with('status', 'Reversal decision recorded.');
    }

    public function requestAdjustment(FeeAssessment $assessment, RequestAdjustmentRequest $request, RequestAdjustmentAction $create): RedirectResponse
    {
        $create->execute($assessment, $request->validated(), $request->user());

        return back()->with('status', 'Adjustment submitted for Admin decision.');
    }

    public function decideAdjustment(FinancialAdjustment $adjustment, Request $request, ApproveAdjustmentAction $decide): RedirectResponse
    {
        Gate::authorize('approve', Payment::class);
        $decide->execute($adjustment, $request->user(), $request->boolean('approve'));

        return back()->with('status', 'Adjustment decision recorded.');
    }

    public function requestRefund(FeeAssessment $assessment, Request $request, RequestRefundAction $create): RedirectResponse
    {
        abort_unless($request->user()->hasRole('accounting'), 403);
        $data = $request->validate(['amount' => ['required', 'decimal:0,2', 'gt:0'], 'reason' => ['required', 'string', 'max:2000']]);
        $create->execute($assessment, $data['amount'], $data['reason'], $request->user());

        return back()->with('status', 'Refund submitted for Admin decision.');
    }

    public function decideRefund(Refund $refund, Request $request, ApproveRefundAction $decide): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean'], 'decision_reason' => ['required', 'string', 'max:2000'], 'release_reference' => ['nullable', 'required_if:approve,1', 'string', 'max:100'], 'released_on' => ['nullable', 'required_if:approve,1', 'date']]);
        $decide->execute($refund, $request->user(), $request->boolean('approve'), $data['release_reference'] ?? null, $data['released_on'] ?? null, $data['decision_reason']);

        return back()->with('status', 'Refund decision recorded.');
    }

    public function requestDiscountCancellation(DiscountAward $award, Request $request, DecideDiscountCancellationAction $action): RedirectResponse
    {
        abort_unless($request->user()->hasRole('accounting'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $action->request($award, $data['reason'], $request->user());

        return back()->with('status', 'Discount cancellation submitted for Admin decision.');
    }

    public function decideDiscountCancellation(DiscountAward $award, Request $request, DecideDiscountCancellationAction $action): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:2000']]);
        $action->decide($award, $request->boolean('approve'), $data['reason'], $request->user());

        return back()->with('status', 'Discount cancellation decision recorded.');
    }

    public function reopenDiscountSlot(DiscountAward $award, Request $request, DecideDiscountCancellationAction $action): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $nextAward = $action->reopenSlot($award, $data['reason'], $request->user());

        return back()->with('status', $nextAward ? 'Slot reopened and awarded to the next eligible enrollment.' : 'Slot reopened; no eligible enrollment is waiting.');
    }

    public function allocateCredit(Payment $payment, Request $request, AllocateStudentCreditAction $allocate): RedirectResponse
    {
        abort_unless($request->user()->hasRole('accounting'), 403);
        $data = $request->validate(['target_assessment_item_id' => ['required', 'integer', 'exists:assessment_items,id'], 'amount' => ['required', 'decimal:0,2', 'gt:0'], 'reason' => ['required', 'string', 'max:2000']]);
        $target = AssessmentItem::query()->findOrFail($data['target_assessment_item_id']);
        $allocate->execute($payment, $target, $data['amount'], $data['reason'], $request->user());

        return back()->with('status', 'Student credit allocated to the selected obligation.');
    }

    public function cancelAssessment(FeeAssessment $assessment, Request $request, CancelDraftFinancialRecordAction $cancel): RedirectResponse
    {
        Gate::authorize('manage', FeeAssessment::class);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $cancel->assessment($assessment, $data['reason'], $request->user());

        return redirect()->route('billing.index')->with('status', 'Draft assessment cancelled; history retained.');
    }

    public function cancelPayment(Payment $payment, Request $request, CancelDraftFinancialRecordAction $cancel): RedirectResponse
    {
        Gate::authorize('manage', Payment::class);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $cancel->payment($payment, $data['reason'], $request->user());

        return back()->with('status', 'Draft payment cancelled; history retained.');
    }
}
