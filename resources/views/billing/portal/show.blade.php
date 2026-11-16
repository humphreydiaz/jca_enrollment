<x-layouts.app title="Financial summary">
    <section class="jca-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black text-brand-navy-900">Financial summary</h1>
                <p class="mt-2 text-slate-600">{{ $assessment->enrollment->student->full_name }} &middot; {{ $assessment->assessment_reference }} &middot;Revision {{ $assessment->revision_number }}</p>
                <div class="mt-2"><x-ui.status-badge :status="$assessment->status" /></div>
            </div>
            <a class="jca-button-secondary" href="{{ route('billing.print.statement', $assessment) }}">Print statement</a>
        </div>
        @if ($siblingDiscount ?? null)
            {{-- D-067: the Registrar confirmed the sibling discount; Accounting applies it as an approved adjustment. --}}
            <p class="mt-4 rounded-lg border border-brand-navy-700/20 bg-brand-blue-50 p-3 text-sm text-brand-navy-900" role="status">
                <strong>Sibling discount confirmed</strong> by {{ $siblingDiscount->confirmer?->name }} on {{ $siblingDiscount->confirmed_at->timezone('Asia/Manila')->format('d M Y') }} (siblings: {{ $siblingDiscount->sibling_summary }}). Apply the school's sibling discount below as a credit adjustment with the reason "Sibling discount".
            </p>
        @endif
        <dl class="mt-6 grid gap-3 sm:grid-cols-3">
            <div><dt class="text-sm text-slate-600">Assessment</dt><dd class="font-bold">PHP {{ number_format((float) $assessment->net_total, 2) }}</dd></div>
            <div><dt class="text-sm text-slate-600">Paid</dt><dd class="font-bold">PHP {{ number_format((float) $balance['paid'], 2) }}</dd></div>
            <div><dt class="text-sm text-slate-600">Amount due</dt><dd class="font-bold">PHP {{ number_format((float) $balance['due'], 2) }}</dd></div>
        </dl>

        <h2 class="mt-8 text-xl font-bold">Fee items</h2>
        <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr><th class="p-2 text-left">Description</th><th class="p-2 text-right">Gross</th><th class="p-2 text-right">Discount</th><th class="p-2 text-right">Net</th></tr></thead><tbody>@foreach ($assessment->items as $item)<tr class="border-t"><td class="p-2">{{ $item->fee_name }}</td><td class="p-2 text-right">PHP {{ number_format((float) $item->gross_amount, 2) }}</td><td class="p-2 text-right">PHP {{ number_format((float) $item->discount_amount, 2) }}</td><td class="p-2 text-right">PHP {{ number_format((float) $item->net_amount, 2) }}</td></tr>@endforeach</tbody></table></div>

        <h2 class="mt-8 text-xl font-bold">Due schedule</h2>
        @if ($assessment->plan)
            <p class="mt-2 text-sm text-slate-600">{{ str($assessment->plan->plan_type)->headline() }}</p>
            <ul class="mt-3 space-y-2">@foreach ($assessment->plan->scheduleItems as $due)<li class="flex justify-between gap-4"><span>{{ $due->label }}&middot; {{ $due->due_on->format('M j, Y') }}</span><strong>PHP {{ number_format((float) $due->amount, 2) }}</strong></li>@endforeach</ul>
        @else
            <p class="mt-2 text-slate-600">No payment plan has been selected.</p>
        @endif

        <h2 class="mt-8 text-xl font-bold">Posted payments</h2>
        <ul class="mt-3 space-y-2">@forelse ($assessment->payments as $payment)<li class="flex flex-wrap justify-between gap-3"><span>{{ $payment->received_on->format('M j, Y') }} &middot; {{ $payment->receipt_reference }}</span><span><strong>PHP {{ number_format((float) $payment->amount, 2) }}</strong> <a class="ml-2 underline" href="{{ route('billing.print.receipt', $payment) }}">Receipt</a></span>@if(auth()->user()->hasRole('accounting'))<form method="post" action="{{ route('billing.reversals.request', $payment) }}" class="flex w-full gap-2">@csrf<input class="jca-input" name="amount" placeholder="Reversal amount" required><input class="jca-input" name="reason" placeholder="Required reason" required><button class="jca-button-secondary">Request reversal</button></form>@endif</li>@empty<li class="text-slate-600">No posted payments.</li>@endforelse</ul>
        <div class="mt-8 flex flex-wrap items-center gap-2"><strong>Exam authorization:</strong><x-ui.status-badge :status="$permitStatus === 'issued' ? 'authorized' : 'not_authorized'" />@if($issuedPermit)<a class="ml-2 underline" href="{{ route('billing.print.permit',$issuedPermit) }}">Print permit</a>@endif</div>
        @if(auth()->user()->hasRole('accounting'))
        @if($assessment->status === 'draft')
        <span id="accounting-actions" class="block scroll-mt-24" aria-hidden="true"></span>
        <div class="mt-8 border-t pt-6"><h2 class="text-xl font-bold">Edit draft assessment</h2>@foreach($availableSchedules as $schedule)<form method="post" action="{{ route('billing.assessments.update',$assessment) }}" class="mt-4 rounded border p-4">@csrf @method('PUT')<input type="hidden" name="fee_schedule_id" value="{{ $schedule->id }}"><p class="font-bold">{{ $schedule->name }} &middot;v{{ $schedule->version }}</p><div class="mt-2 grid gap-2 sm:grid-cols-2">@foreach($schedule->items as $item)<label><input type="checkbox" name="selected_items[]" value="{{ $item->id }}" @checked($item->is_required || $assessment->items->contains('fee_type_id',$item->fee_type_id)) @disabled($item->is_required)> {{ $item->feeType->name }}&middot;PHP {{ number_format((float)$item->amount,2) }}</label>@if($item->is_required)<input type="hidden" name="selected_items[]" value="{{ $item->id }}">@endif @endforeach</div><label class="mt-3 block">Revision reason<input class="jca-input" name="revision_reason" value="{{ $assessment->revision_reason }}"></label><button class="jca-button-secondary mt-3">Save draft items</button></form>@endforeach<form method="post" action="{{ route('billing.assessments.finalize',$assessment) }}" class="mt-4">@csrf<button class="jca-button-primary">Finalize immutable assessment</button></form><form method="post" action="{{ route('billing.assessments.cancel',$assessment) }}" class="mt-4">@csrf<label>Cancellation reason<input class="jca-input" name="reason" required></label><button class="jca-button-secondary mt-2">Cancel draft assessment</button></form></div>
        @elseif($assessment->status === 'finalized')
        <div class="mt-8 grid gap-6 border-t pt-6 lg:grid-cols-2" id="accounting-actions">
            <form method="post" action="{{ route('billing.plans.select', $assessment) }}" class="rounded-xl border border-border p-4">@csrf<h2 class="text-lg font-bold">Step 3: Choose payment plan</h2><p class="mt-1 text-sm text-muted">Only active plans configured for {{ $assessment->enrollment->gradeLevel?->name }} are shown.</p><label class="mt-3 block">Plan<select class="jca-input" name="plan_type" required @disabled($availablePlanOptions->isEmpty())>@foreach($availablePlanOptions as $option)<option value="{{ $option['code'] }}" @selected(old('plan_type') === $option['code'])>{{ $option['name'] }}</option>@endforeach</select></label>@error('plan_type')<p class="mt-2 text-sm font-semibold text-brand-red-600" role="alert">{{ $message }}</p>@enderror @if($availablePlanOptions->isEmpty())<p class="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-950">No active payment plan is configured for this school year and grade. Ask an Admin to activate one.</p>@endif<button class="jca-button-primary mt-3" @disabled($availablePlanOptions->isEmpty())>Generate due schedule</button></form>
            <form method="post" action="{{ route('billing.payment-to-follow', $assessment) }}">@csrf<h2 class="text-lg font-bold">Payment-to-follow</h2><label class="mt-2 block">Approved until<input class="jca-input" type="date" name="approved_until" required></label><label class="mt-2 block">Reason (required for extension)<textarea class="jca-input" name="reason"></textarea></label><button class="jca-button-primary mt-3">Record arrangement</button></form>
            <form method="post" action="{{ route('billing.payments.create', $assessment) }}" class="rounded-xl border border-border p-4">@csrf<h2 class="text-lg font-bold">Step 4: Record received payment</h2><p class="mt-1 text-sm text-muted">For payments received outside the system, including cash, cheque, bank transfer, or e-wallet. Save the payment as a draft, then review and post it on the Billing page before an official receipt is assigned.</p><label class="mt-3 block">Date received<input class="jca-input" type="date" name="received_on" required></label><label class="mt-2 block">Amount<input class="jca-input" name="amount" inputmode="decimal" required></label><label class="mt-2 block">Method<select class="jca-input" name="method"><option value="cash">Cash</option><option value="cheque">Cheque</option><option value="bank_transfer">Bank deposit/transfer</option><option value="e_wallet">E-wallet</option><option value="other">Other offline</option></select></label><label class="mt-2 block">Payer<input class="jca-input" name="payer_name" required></label><label class="mt-2 block">Method reference<input class="jca-input" name="method_reference"></label><label class="mt-2 block">Bank name<input class="jca-input" name="bank_name"></label><label class="mt-2 block">Method description<input class="jca-input" name="method_description"></label><label class="mt-2 block">Allocate to<select class="jca-input" name="assessment_item_id">@foreach($assessment->items as $item)<option value="{{ $item->id }}">{{ $item->fee_name }}</option>@endforeach</select></label><button class="jca-button-primary mt-3">Save draft payment for review</button></form>
            <div><form method="post" action="{{ route('billing.adjustments.request', $assessment) }}">@csrf<h2 class="text-lg font-bold">Request adjustment</h2><select class="jca-input mt-2" name="adjustment_type"><option value="debit">Debit</option><option value="credit">Credit</option><option value="waiver">Waiver</option></select><input class="jca-input mt-2" name="amount" placeholder="Amount" required><input class="jca-input mt-2" name="reason" placeholder="Required reason" required><button class="jca-button-secondary mt-2">Submit adjustment</button></form></div>
            @php
    $requestableExamPeriods = $examPeriods->filter(function ($period) use ($examPermitEligibility) {
        $canRequest = data_get(
            $examPermitEligibility,
            $period->id . '.can_request_override',
            false
        );

        $opensOn = $period->starts_on?->copy()->startOfMonth();

        $closesOn = $period->ends_on;

        return $canRequest
            && $opensOn !== null
            && $opensOn->lte(today())
            && ($closesOn === null || $closesOn->gte(today()));
    });
@endphp

@if($requestableExamPeriods->isNotEmpty())
<div id="permit-override-request" class="rounded-xl border border-border bg-white p-4">
    <h2 class="text-lg font-bold text-brand-navy-900">
        Request Exam Permit Exception
    </h2>

    <p class="mt-1 text-sm text-muted">
        Request Admin approval only when the required examination payment is insufficient.
    </p>

    <form method="POST"
          action="{{ route('billing.permit-overrides.request', $assessment->enrollment) }}"
          class="mt-4 space-y-3">
        @csrf

        <label class="jca-label">
            Examination
            <select name="exam_period_id" class="jca-input mt-1" required>
                <option value="">Select examination</option>
                @foreach($requestableExamPeriods as $period)
                    <option value="{{ $period->id }}"
                        @selected(old('exam_period_id') == $period->id)>
                        {{ $period->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="jca-label">
            Reason Category
            <select name="reason_category" class="jca-input mt-1" required>
                <option value="">Select reason</option>
                <option value="promissory_note">Promissory Note</option>
                <option value="financial_hardship">Financial Hardship</option>
                <option value="billing_correction">Billing Correction</option>
                <option value="emergency">Emergency</option>
                <option value="statutory_entitlement">Statutory Entitlement</option>
            </select>
        </label>

        <label class="jca-label">
            Confidential Reason
            <textarea name="confidential_reason"
                      class="jca-input mt-1"
                      rows="3"
                      maxlength="4000"
                      required>{{ old('confidential_reason') }}</textarea>
        </label>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="jca-label">
                Effective From
                <input type="date"
                       name="effective_from"
                       class="jca-input mt-1"
                       value="{{ old('effective_from', today()->format('Y-m-d')) }}"
                       required>
            </label>

            <label class="jca-label">
                Expires On (Optional)
                <input type="date"
                       name="expires_on"
                       class="jca-input mt-1"
                       value="{{ old('expires_on') }}">
            </label>
        </div>

        <label class="jca-label">
            Supporting Document Reference (Optional)
            <input type="text"
                   name="supporting_document_reference"
                   class="jca-input mt-1"
                   maxlength="255">
        </label>

        <button type="submit" class="jca-button-primary">
            Submit for Admin Approval
        </button>
    </form>
</div>
@endif
<div>
    <h2 class="text-lg font-bold">Permit issuance</h2>

    @php
        $issuedPermitsByPeriod = \App\Modules\Billing\Models\ExamPermit::query()
            ->where('enrollment_id', $assessment->enrollment_id)
            ->where('status', 'issued')
            ->orderBy('id')
            ->get()
            ->keyBy('exam_period_id');
    @endphp

    <div class="mt-2 space-y-3">
        @forelse($examPeriods as $period)
            @php
                $periodPermit = $issuedPermitsByPeriod->get($period->id);
                $opensOn = $period->starts_on?->copy()->startOfMonth();
                $closesOn = $period->ends_on;

                $canIssue = $opensOn !== null
                    && $closesOn !== null
                    && today()->gte($opensOn)
                    && today()->lte($closesOn);
            @endphp

            @if($periodPermit)
                <a class="jca-button-secondary inline-block"
                   href="{{ route('billing.print.permit', $periodPermit) }}">
                    Print Permit: {{ $period->name }}
                </a>
            @else
                <form method="POST"
                      action="{{ route('billing.permits.issue', [$assessment, $period]) }}">
                    @csrf

                    <button type="submit"
                        class="jca-button-secondary {{ $canIssue ? '' : 'cursor-not-allowed opacity-50' }}"
                        @disabled(! $canIssue)>
                        Calculate and issue: {{ $period->name }}
                    </button>

                    @unless($canIssue)
                        <p class="mt-1 text-xs text-slate-600">
                            @if($opensOn && today()->lt($opensOn))
                                Not yet open - available from {{ $opensOn->format('M j, Y') }}.
                            @else
                                Closed - examination ended {{ $closesOn?->format('M j, Y') }}.
                            @endif
                        </p>
                    @endunless
                </form>
            @endif

        @empty
            <p class="text-slate-600">No exam periods configured.</p>
        @endforelse
    </div>
</div>
            @foreach($discountAwards->where('status','awarded') as $award)<form method="post" action="{{ route('billing.discounts.cancellation.request',$award) }}">@csrf<h2 class="text-lg font-bold">Request discount cancellation</h2><input class="jca-input mt-2" name="reason" placeholder="Required correction reason" required><button class="jca-button-secondary mt-2">Submit request</button></form>@endforeach
        </div>
        @else
        <div class="mt-8 border-t pt-6" id="accounting-actions">
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                <p class="font-bold">This assessment is {{ $assessment->status }} and can no longer take a payment plan or payment.</p>
                @if($assessment->status === 'cancelled')
                    <p class="mt-1">Cancelled records are kept for history. To bill this student, create a new assessment from the <a class="font-semibold underline" href="{{ route('billing.index') }}#awaiting-assessment">Students awaiting an assessment</a> list.</p>
                @else
                    <p class="mt-1">This is a superseded revision; the current assessment replaces it.</p>
                @endif
            </div>
        </div>
        @endif
        @endif
        @if(auth()->user()->hasRole('admin') && $issuedPermit)<form method="post" action="{{ route('billing.permits.revoke',$issuedPermit) }}" class="mt-6 border-t pt-4">@csrf<label>Permit revocation reason<input class="jca-input" name="reason" required></label><button class="jca-button-secondary mt-2">Revoke issued permit</button></form>@endif
    </section>

    {{-- Format money for display while sending plain decimals to Laravel. --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('form input[name="amount"]').forEach(function (input) {
                input.type = 'text';
                input.inputMode = 'decimal';

                function formatMoney(value) {
                    const cleaned = String(value).replace(/,/g, '').replace(/[^0-9.]/g, '');
                    const parts = cleaned.split('.');
                    const whole = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    return parts.length > 1
                        ? whole + '.' + parts.slice(1).join('').slice(0, 2)
                        : whole;
                }

                input.value = formatMoney(input.value);
                input.addEventListener('input', function () {
                    const position = input.selectionStart;
                    const commasBefore = (input.value.slice(0, position).match(/,/g) || []).length;
                    input.value = formatMoney(input.value);
                    const newPosition = Math.min(input.value.length, position - commasBefore +
                        (input.value.slice(0, position).match(/,/g) || []).length);
                    input.setSelectionRange(newPosition, newPosition);
                });

                input.addEventListener('blur', function () {
                    const numeric = input.value.replace(/,/g, '');
                    if (numeric !== '' && Number.isFinite(Number(numeric))) {
                        input.value = formatMoney(Number(numeric).toFixed(2));
                    }
                });

                const form = input.closest('form');
                if (form) {
                    form.addEventListener('submit', function () {
                        const numeric = input.value.replace(/,/g, '');
                        if (numeric !== '' && Number.isFinite(Number(numeric))) {
                            input.value = Number(numeric).toFixed(2);
                        } else {
                            input.value = numeric;
                        }
                    });
                }
            });
        });
    </script>
</x-layouts.app>
