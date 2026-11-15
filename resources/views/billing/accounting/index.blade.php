<x-layouts.app title="Billing">
    <div class="space-y-8">
        {{-- Page header --}}
        <section class="jca-card overflow-hidden">
            <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <p class="text-sm font-bold text-brand-orange-500">Accounting</p>
                    <h1 class="mt-1 text-3xl font-black tracking-tight text-brand-navy-900 sm:text-4xl">Student billing</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-muted sm:text-base">Find a student, create or continue the assessment, choose a payment plan, then record and post the received payment.</p>
                </div>
                <div class="hidden size-28 place-items-center rounded-full bg-brand-blue-100 text-brand-navy-700 lg:grid" aria-hidden="true">
                    <x-ui.icon name="billing" size="size-12" />
                </div>
            </div>
            <div class="h-1.5 bg-gradient-to-r from-brand-navy-700 via-brand-orange-500 to-brand-gold-500"></div>
        </section>

        {{-- Workflow steps --}}
        <div class="grid gap-4 sm:grid-cols-4">
            @foreach ([['1', 'Create assessment', 'Build the student fee record from a published schedule.', 'finance'], ['2', 'Finalize charges', 'Lock the assessment so the student can view their balance.', 'check-all'], ['3', 'Choose payment plan', 'Select installment terms for the finalized assessment.', 'billing'], ['4', 'Record payment', 'Enter cash, cheque, or bank payments and post receipts.', 'reports']] as [$number, $label, $desc, $icon])
                <div class="jca-card flex items-start gap-4 p-5">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700">
                        <x-ui.icon :name="$icon" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-black text-brand-navy-900"><span class="mr-1.5 inline-flex size-5 items-center justify-center rounded-full bg-brand-navy-900 text-[10px] font-black text-white">{{ $number }}</span>{{ $label }}</p>
                        <p class="mt-1 text-xs text-muted">{{ $desc }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Search & filter bar --}}
        <section class="jca-card overflow-hidden" aria-label="Search and filter">
            <form method="GET" action="{{ route('billing.index') }}" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-end sm:p-6" role="search">
                <div class="flex-1">
                    <label class="jca-label" for="billing-search">Search</label>
                    <input id="billing-search" class="jca-input mt-1" type="search" name="search" value="{{ $search }}" placeholder="Student name or number">
                </div>
                <div class="w-full sm:w-48">
                    <label class="jca-label" for="billing-grade">Grade level</label>
                    <select id="billing-grade" class="jca-input mt-1" name="grade">
                        <option value="">All grades</option>
                        @foreach ($gradeLevels as $grade)
                            <option value="{{ $grade->id }}" @selected($gradeFilter == $grade->id)>{{ $grade->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button class="jca-button-primary whitespace-nowrap" type="submit"><x-ui.icon name="search" size="size-4" /> Filter</button>
                    @if ($search !== '' || $gradeFilter)
                        <a class="jca-button-secondary whitespace-nowrap" href="{{ route('billing.index') }}">Clear</a>
                    @endif
                </div>
            </form>
            @if ($search !== '' || $gradeFilter)
                <div class="flex flex-wrap items-center gap-2 border-t border-border px-5 py-3 text-sm text-muted sm:px-6">
                    <span>Filtering by:</span>
                    @if ($search !== '')
                        <span class="inline-flex items-center gap-1 rounded-full bg-brand-blue-100 px-3 py-0.5 text-xs font-bold text-brand-navy-900">"{{ $search }}"</span>
                    @endif
                    @if ($gradeFilter)
                        <span class="inline-flex items-center gap-1 rounded-full bg-brand-blue-100 px-3 py-0.5 text-xs font-bold text-brand-navy-900">{{ $gradeLevels->firstWhere('id', $gradeFilter)?->name }}</span>
                    @endif
                </div>
            @endif
        </section>

        {{-- Students awaiting assessment --}}
        @if ($canViewAssessmentQueue)
            <section class="jca-card overflow-hidden" id="awaiting-assessment">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-border px-5 py-4 sm:px-6">
                    <div>
                        <h2 class="text-xl font-black text-brand-navy-900">1. Students awaiting an assessment</h2>
                        <p class="mt-1 text-sm text-muted">Approved or enrolled students without a fee assessment. {{ $assessableEnrollments instanceof \Illuminate\Pagination\AbstractPaginator ? $assessableEnrollments->total() : $assessableEnrollments->count() }} total.</p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-brand-blue-100 px-4 py-1.5 text-sm font-black text-brand-navy-700">
                        <x-ui.icon name="finance" size="size-4" />
                        Step 1
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-muted">
                            <tr>
                                <th class="px-5 py-3 sm:px-6">Student</th>
                                <th class="px-4 py-3">Grade level</th>
                                <th class="px-4 py-3">Academic year</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse ($assessableEnrollments as $enrollment)
                                @php
                                    $matches = $publishedSchedules->where('academic_year_id', $enrollment->academic_year_id)->filter(fn ($schedule) => $schedule->grade_level_id === null || $schedule->grade_level_id === $enrollment->grade_level_id);
                                @endphp
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-5 py-4 sm:px-6">
                                        <p class="font-bold text-brand-navy-900">{{ $enrollment->student->full_name }}</p>
                                        <p class="text-xs text-muted">{{ $enrollment->student->student_number ?: 'No student number' }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center rounded-full bg-brand-blue-100 px-2.5 py-0.5 text-xs font-bold text-brand-navy-700">{{ $enrollment->gradeLevel->name }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-muted">{{ $enrollment->academicYear->name }}</td>
                                    <td class="px-4 py-4"><x-ui.status-badge :status="$enrollment->status" /></td>
                                    <td class="px-4 py-4 text-right">
                                        @if ($matches->isEmpty())
                                            <span class="inline-flex flex-col items-end gap-1 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-800">
                                                <span class="inline-flex items-center gap-1.5 font-bold"><x-ui.icon name="warning" size="size-3.5" /> Blocked: no published fee schedule</span>
                                                <span>A payment plan by itself is not enough.</span>
                                            </span>
                                        @elseif ($canManageAssessments)
                                            <form method="post" action="{{ route('billing.assessments.create', $enrollment) }}" class="flex items-center justify-end gap-2">
                                                @csrf
                                                <select class="jca-input h-8 py-0 text-xs" name="fee_schedule_id" required>
                                                    @foreach ($matches as $schedule)
                                                        <option value="{{ $schedule->id }}">{{ $schedule->name }} v{{ $schedule->version }}</option>
                                                    @endforeach
                                                </select>
                                                <button class="jca-button-primary whitespace-nowrap text-xs">Create</button>
                                            </form>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-xs font-bold text-green-700">
                                                <x-ui.icon name="check-all" size="size-3.5" /> Ready for Accounting assessment
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-8">
                                        <x-ui.empty-state icon="finance" title="{{ $search !== '' || $gradeFilter ? 'No matches' : 'All caught up' }}" description="{{ $search !== '' || $gradeFilter ? 'No eligible students matched the current filter.' : 'Every approved enrollment already has an assessment.' }}" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($assessableEnrollments instanceof \Illuminate\Pagination\AbstractPaginator && $assessableEnrollments->hasPages())
                    <div class="border-t border-border px-5 py-4 sm:px-6">{{ $assessableEnrollments->links() }}</div>
                @endif
            </section>
        @endif

        {{-- Existing assessments --}}
        <section class="jca-card overflow-hidden" id="assessments">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-border px-5 py-4 sm:px-6">
                <div>
                    <h2 class="text-xl font-black text-brand-navy-900">Existing assessments</h2>
                    <p class="mt-1 text-sm text-muted">Open a student's record to finalize charges, choose a plan, or record the next payment.</p>
                </div>
                <span class="inline-flex items-center gap-2 rounded-full bg-brand-blue-100 px-4 py-1.5 text-sm font-black text-brand-navy-700">
                    <x-ui.icon name="billing" size="size-4" />
                    Steps 2-4
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-muted">
                        <tr>
                            <th class="px-5 py-3 sm:px-6">Student</th>
                            <th class="px-4 py-3">Reference</th>
                            <th class="px-4 py-3">Grade</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Balance due</th>
                            <th class="px-4 py-3 text-right">Next action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($assessments as $assessment)
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="font-bold text-brand-navy-900">{{ $assessment->enrollment->student->full_name }}</p>
                                    <p class="text-xs text-muted">{{ $assessment->enrollment->academicYear?->name }}</p>
                                </td>
                                <td class="px-4 py-4 font-mono text-xs text-muted">{{ $assessment->assessment_reference }}</td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center rounded-full bg-brand-blue-100 px-2.5 py-0.5 text-xs font-bold text-brand-navy-700">{{ $assessment->enrollment->gradeLevel?->name }}</span>
                                </td>
                                <td class="px-4 py-4">
    @if (
        $assessment->status === 'finalized'
        && round((float) $balances[$assessment->id]['due'], 2) == 0
    )
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold"
              style="background-color: #dcfce7; color: #166534;">
            Paid
        </span>
    @else
        <x-ui.status-badge :status="$assessment->status" />
    @endif
</td>
                                <td class="px-4 py-4 text-right font-bold tabular-nums {{ (float) $balances[$assessment->id]['due'] > 0 ? 'text-brand-orange-500' : 'text-brand-navy-900' }}">PHP {{ number_format((float) $balances[$assessment->id]['due'], 2) }}</td>
                                <td class="px-4 py-4 text-right">
                                    @if ($assessment->status === 'draft')
                                        <a class="jca-button-secondary whitespace-nowrap text-xs" href="{{ route('billing.portal.show', $assessment) }}#accounting-actions">Finalize</a>
                                    @elseif ($assessment->status === 'finalized')
                                        <a class="jca-button-secondary whitespace-nowrap text-xs" href="{{ route('billing.portal.show', $assessment) }}#accounting-actions">{{ $assessment->plan ? 'Record payment' : 'Choose plan' }}</a>
                                    @elseif ($assessment->status === 'cancelled')
                                        <a class="whitespace-nowrap text-xs font-semibold text-muted underline" href="{{ route('billing.index') }}#awaiting-assessment">Re-assess in Step 1</a>
                                    @else
                                        <span class="whitespace-nowrap text-xs font-semibold text-muted">Superseded</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8">
                                    <x-ui.empty-state icon="billing" title="{{ $search !== '' || $gradeFilter ? 'No matches' : 'No assessments yet' }}" description="{{ $search !== '' || $gradeFilter ? 'No assessments matched the current filter.' : 'Assessments will appear here once created.' }}" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($assessments->hasPages())
                <div class="border-t border-border px-5 py-4 sm:px-6">{{ $assessments->links() }}</div>
            @endif
        </section>

        {{-- Draft payments --}}
        @if (auth()->user()->hasRole('accounting'))
            <section class="jca-card overflow-hidden" id="draft-payments">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-border px-5 py-4 sm:px-6">
                    <div>
                        <h2 class="text-xl font-black text-brand-navy-900">Draft payments</h2>
                        <p class="mt-1 text-sm text-muted">Drafts do not reduce the balance. Review, then post to assign the official receipt.</p>
                    </div>
                    @if ($draftPayments->isNotEmpty())
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-orange-500/10 px-3 py-1 text-sm font-black text-brand-orange-500">{{ $draftPayments->count() }} pending</span>
                    @endif
                </div>

                <div class="divide-y divide-border">
                    @forelse ($draftPayments as $payment)
                        <div class="p-5 sm:p-6">
                            <div class="flex flex-wrap items-center gap-4">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700">
                                    <x-ui.icon name="billing" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-brand-navy-900">{{ $payment->assessment->enrollment->student->full_name }}</p>
                                    <p class="text-sm text-muted">{{ str($payment->method)->headline() }} &middot; PHP {{ number_format((float) $payment->amount, 2) }}</p>
                                </div>
                                <x-ui.status-badge status="draft" />
                            </div>

                            <form method="post" action="{{ route('billing.payments.update', $payment) }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                @csrf @method('PUT')
                                <div>
                                    <label class="jca-label">Received on</label>
                                    <input class="jca-input mt-1" type="date" name="received_on" value="{{ $payment->received_on->format('Y-m-d') }}" required>
                                </div>
                                <div>
                                    <label class="jca-label">Amount</label>
                                    <input class="jca-input mt-1" name="amount" value="{{ $payment->amount }}" required>
                                </div>
                                <div>
                                    <label class="jca-label">Method</label>
                                    <select class="jca-input mt-1" name="method">
                                        <option value="cash" @selected($payment->method === 'cash')>Cash</option>
                                        <option value="cheque" @selected($payment->method === 'cheque')>Cheque</option>
                                        <option value="bank_transfer" @selected($payment->method === 'bank_transfer')>Bank transfer</option>
                                        <option value="e_wallet" @selected($payment->method === 'e_wallet')>E-wallet</option>
                                        <option value="other" @selected($payment->method === 'other')>Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="jca-label">Payer name</label>
                                    <input class="jca-input mt-1" name="payer_name" value="{{ $payment->payer_name }}" required>
                                </div>
                                <div>
                                    <label class="jca-label">Method reference</label>
                                    <input class="jca-input mt-1" name="method_reference" value="{{ $payment->method_reference }}" placeholder="Reference number">
                                </div>
                                <div>
                                    <label class="jca-label">Bank</label>
                                    <input class="jca-input mt-1" name="bank_name" value="{{ $payment->bank_name }}" placeholder="Bank name">
                                </div>
                                <div>
                                    <label class="jca-label">Description</label>
                                    <input class="jca-input mt-1" name="method_description" value="{{ $payment->method_description }}" placeholder="Additional details">
                                </div>
                                <div class="flex items-end">
                                    <button class="jca-button-secondary w-full">Save draft</button>
                                </div>
                            </form>

                            <div class="mt-4 flex flex-wrap gap-3 border-t border-border pt-4">
                                <form method="post" action="{{ route('billing.payments.post', $payment) }}">
                                    @csrf
                                    <button class="jca-button-primary">Post and assign receipt</button>
                                </form>
                                <form method="post" action="{{ route('billing.evidence.store', $payment) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <div>
                                        <label class="jca-label">Evidence (PDF/JPG/PNG, max 5 MB)</label>
                                        <input class="jca-input mt-1" type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png" required>
                                    </div>
                                    <button class="jca-button-secondary">Upload</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="p-8">
                            <x-ui.empty-state icon="billing" title="No draft payments" description="Draft payments will appear here when recorded but not yet posted." />
                        </div>
                    @endforelse
                </div>
            </section>
        @endif

        {{-- Pending financial decisions (admin only) --}}
        @if (auth()->user()->hasRole('admin'))
            @php
                $hasPending = $pendingReversals->isNotEmpty() || $pendingAdjustments->isNotEmpty() || $pendingRefunds->isNotEmpty() || $pendingOverrides->isNotEmpty() || $pendingDiscountAwards->isNotEmpty() || $cancelledDiscountAwards->isNotEmpty();
                $pendingCount = $pendingReversals->count() + $pendingAdjustments->count() + $pendingRefunds->count() + $pendingOverrides->count() + $pendingDiscountAwards->count() + $cancelledDiscountAwards->count();
            @endphp
            <section class="jca-card overflow-hidden" id="pending-decisions">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-border px-5 py-4 sm:px-6">
                    <div>
                        <h2 class="text-xl font-black text-brand-navy-900">Pending financial decisions</h2>
                        <p class="mt-1 text-sm text-muted">Reversals, adjustments, refunds, and overrides requiring admin approval.</p>
                    </div>
                    @if ($hasPending)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-red-600/10 px-3 py-1 text-sm font-black text-brand-red-600">{{ $pendingCount }} pending</span>
                    @endif
                </div>

                <div class="grid gap-4 p-5 sm:p-6 lg:grid-cols-2">
                    @foreach ($pendingReversals as $record)
                        <form method="post" action="{{ route('billing.reversals.decide', $record) }}" class="rounded-xl border border-border p-5">
                            @csrf
                            <div class="flex items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-red-600/10 text-brand-red-600"><x-ui.icon name="billing" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-brand-navy-900">Reversal request</p>
                                    <p class="mt-1 text-sm text-muted">{{ $record->payment->payment_reference }} &middot; PHP {{ number_format((float) $record->amount, 2) }}</p>
                                    <p class="mt-2 text-sm">{{ $record->reason }}</p>
                                </div>
                            </div>
                            <div class="mt-4 flex gap-2">
                                <button class="jca-button-primary" name="approve" value="1">Approve</button>
                                <button class="jca-button-secondary" name="approve" value="0">Reject</button>
                            </div>
                        </form>
                    @endforeach

                    @foreach ($pendingAdjustments as $record)
                        <form method="post" action="{{ route('billing.adjustments.decide', $record) }}" class="rounded-xl border border-border p-5">
                            @csrf
                            <div class="flex items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-orange-500/10 text-brand-orange-500"><x-ui.icon name="finance" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-brand-navy-900">{{ str($record->adjustment_type)->headline() }}</p>
                                    <p class="mt-1 text-sm text-muted">PHP {{ number_format((float) $record->amount, 2) }}</p>
                                    <p class="mt-2 text-sm">{{ $record->reason }}</p>
                                </div>
                            </div>
                            <div class="mt-4 flex gap-2">
                                <button class="jca-button-primary" name="approve" value="1">Approve</button>
                                <button class="jca-button-secondary" name="approve" value="0">Reject</button>
                            </div>
                        </form>
                    @endforeach

                    @foreach ($pendingRefunds as $record)
                        <form method="post" action="{{ route('billing.refunds.decide', $record) }}" class="rounded-xl border border-border p-5">
                            @csrf
                            <div class="flex items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-orange-500/10 text-brand-orange-500"><x-ui.icon name="billing" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-brand-navy-900">Refund request</p>
                                    <p class="mt-1 text-sm text-muted">PHP {{ number_format((float) $record->amount, 2) }}</p>
                                    <p class="mt-2 text-sm">{{ $record->reason }}</p>
                                </div>
                            </div>
                            <div class="mt-4 space-y-3">
                                <div>
                                    <label class="jca-label">Decision reason</label>
                                    <input class="jca-input mt-1" name="decision_reason" required>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="jca-label">Release reference</label>
                                        <input class="jca-input mt-1" name="release_reference">
                                    </div>
                                    <div>
                                        <label class="jca-label">Release date</label>
                                        <input class="jca-input mt-1" type="date" name="released_on">
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button class="jca-button-primary" name="approve" value="1">Approve</button>
                                    <button class="jca-button-secondary" name="approve" value="0">Reject</button>
                                </div>
                            </div>
                        </form>
                    @endforeach

                    @foreach ($pendingOverrides as $record)
                        <form method="post" action="{{ route('billing.permit-overrides.decide', $record) }}" class="rounded-xl border border-border p-5">
                            @csrf
                            <div class="flex items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700"><x-ui.icon name="check-all" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-brand-navy-900">Permit exception</p>
                                    <p class="mt-1 text-sm text-muted">{{ str($record->reason_category)->headline() }}</p>
                                </div>
                            </div>
                            <div class="mt-4 space-y-3">
                                <div>
                                    <label class="jca-label">Decision reason</label>
                                    <input class="jca-input mt-1" name="decision_reason" placeholder="Required" required>
                                </div>
                                <div class="flex gap-2">
                                    <button class="jca-button-primary" name="approve" value="1">Approve</button>
                                    <button class="jca-button-secondary" name="approve" value="0">Reject</button>
                                </div>
                            </div>
                        </form>
                    @endforeach

                    @foreach ($pendingDiscountAwards as $award)
                        <form method="post" action="{{ route('billing.discounts.cancellation.decide', $award) }}" class="rounded-xl border border-border p-5">
                            @csrf
                            <div class="flex items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-gold-500/10 text-brand-gold-500"><x-ui.icon name="finance" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-brand-navy-900">Discount cancellation</p>
                                    <p class="mt-1 text-sm text-muted">Rank {{ $award->rank }} &middot; PHP {{ number_format((float) $award->amount, 2) }}</p>
                                </div>
                            </div>
                            <div class="mt-4 space-y-3">
                                <div>
                                    <label class="jca-label">Decision reason</label>
                                    <input class="jca-input mt-1" name="reason" placeholder="Required" required>
                                </div>
                                <div class="flex gap-2">
                                    <button class="jca-button-primary" name="approve" value="1">Approve</button>
                                    <button class="jca-button-secondary" name="approve" value="0">Reject</button>
                                </div>
                            </div>
                        </form>
                    @endforeach

                    @foreach ($cancelledDiscountAwards as $award)
                        <form method="post" action="{{ route('billing.discounts.reopen-slot', $award) }}" class="rounded-xl border border-border p-5">
                            @csrf
                            <div class="flex items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-gold-500/10 text-brand-gold-500"><x-ui.icon name="finance" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-brand-navy-900">Cancelled discount slot</p>
                                    <p class="mt-1 text-sm text-muted">Historical rank {{ $award->rank }}</p>
                                </div>
                            </div>
                            <div class="mt-4 space-y-3">
                                <div>
                                    <label class="jca-label">Reopening reason</label>
                                    <input class="jca-input mt-1" name="reason" placeholder="Required" required>
                                </div>
                                <button class="jca-button-secondary">Reopen for next eligible</button>
                            </div>
                        </form>
                    @endforeach

                    @unless ($hasPending)
                        <div class="lg:col-span-2">
                            <x-ui.empty-state icon="check-all" title="No pending decisions" description="All financial decisions have been resolved." />
                        </div>
                    @endunless
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>
