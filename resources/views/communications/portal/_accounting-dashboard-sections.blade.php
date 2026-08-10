@if ($activeYear)
    <section class="jca-card overflow-hidden" aria-labelledby="acct-year-title">
        <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-6">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700">
                    <x-ui.icon name="academics" />
                </span>
                <div>
                    <h2 id="acct-year-title" class="font-black text-brand-navy-900">{{ $activeYear->name }}</h2>
                    <p class="text-sm text-muted">{{ $activeYear->starts_on->format('M j, Y') }} &ndash; {{ $activeYear->ends_on->format('M j, Y') }}</p>
                </div>
            </div>
            <x-ui.status-badge :status="$activeYear->status" />
        </div>
    </section>
@endif

<section aria-labelledby="acct-collections-title">
    <div>
        <h2 id="acct-collections-title" class="text-xl font-black text-brand-navy-900">Collections summary</h2>
        <p class="mt-1 text-sm text-muted">Posted payment totals for the current period.</p>
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <div class="jca-card p-5">
            <p class="text-sm font-bold text-muted">Today</p>
            <p class="mt-2 text-2xl font-black tabular-nums text-brand-navy-900">{{ number_format($collectionSummary['posted_today'], 2) }}</p>
        </div>
        <div class="jca-card p-5">
            <p class="text-sm font-bold text-muted">This week</p>
            <p class="mt-2 text-2xl font-black tabular-nums text-brand-navy-900">{{ number_format($collectionSummary['posted_this_week'], 2) }}</p>
        </div>
        <div class="jca-card p-5">
            <p class="text-sm font-bold text-muted">This month</p>
            <p class="mt-2 text-2xl font-black tabular-nums text-brand-navy-900">{{ number_format($collectionSummary['posted_this_month'], 2) }}</p>
        </div>
    </div>
</section>

<section aria-labelledby="acct-overview-title">
    <div>
        <h2 id="acct-overview-title" class="text-xl font-black text-brand-navy-900">Financial overview</h2>
        <p class="mt-1 text-sm text-muted">Assessment and payment status across all periods.</p>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <a href="{{ route('billing.index') }}" class="jca-card group flex flex-col p-5 transition hover:-translate-y-0.5 hover:border-brand-navy-700 hover:shadow-lg">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700 transition group-hover:bg-brand-navy-700 group-hover:text-white">
                    <x-ui.icon name="finance" />
                </span>
                <h3 class="font-extrabold text-brand-navy-900">Assessments</h3>
            </div>
            <dl class="mt-4 flex-1 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-muted">Draft</dt>
                    <dd class="font-bold tabular-nums {{ ($assessmentCounts['draft'] ?? 0) > 0 ? 'text-brand-orange-500' : 'text-brand-navy-900' }}">{{ $assessmentCounts['draft'] ?? 0 }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted">Finalized</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $assessmentCounts['finalized'] ?? 0 }}</dd>
                </div>
                <div class="flex justify-between border-t border-border pt-2">
                    <dt class="text-muted">Total assessed</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ number_format($assessmentSummary['total_assessed'], 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted">Needs assessment</dt>
                    <dd class="font-bold tabular-nums {{ $needsAssessment > 0 ? 'text-brand-orange-500' : 'text-brand-navy-900' }}">{{ $needsAssessment }}</dd>
                </div>
            </dl>
        </a>

        <a href="{{ route('billing.index') }}" class="jca-card group flex flex-col p-5 transition hover:-translate-y-0.5 hover:border-brand-navy-700 hover:shadow-lg">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700 transition group-hover:bg-brand-navy-700 group-hover:text-white">
                    <x-ui.icon name="billing" />
                </span>
                <h3 class="font-extrabold text-brand-navy-900">Payments</h3>
            </div>
            <dl class="mt-4 flex-1 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-muted">Draft</dt>
                    <dd class="font-bold tabular-nums {{ ($paymentCounts['draft'] ?? 0) > 0 ? 'text-brand-orange-500' : 'text-brand-navy-900' }}">{{ $paymentCounts['draft'] ?? 0 }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted">Posted</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $paymentCounts['posted'] ?? 0 }}</dd>
                </div>
                <div class="flex justify-between border-t border-border pt-2">
                    <dt class="text-muted">Total collected</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ number_format($assessmentSummary['total_collected'], 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted">Cancelled</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $paymentCounts['cancelled'] ?? 0 }}</dd>
                </div>
            </dl>
        </a>

        @if ($paymentMethods->isNotEmpty())
            <div class="jca-card flex flex-col p-5">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700">
                        <x-ui.icon name="reports" />
                    </span>
                    <h3 class="font-extrabold text-brand-navy-900">By payment method</h3>
                </div>
                <dl class="mt-4 flex-1 space-y-2 text-sm">
                    @foreach ($paymentMethods as $pm)
                        <div class="flex justify-between">
                            <dt class="text-muted">{{ str($pm->method)->headline() }} <span class="text-xs">({{ $pm->total }})</span></dt>
                            <dd class="font-bold tabular-nums text-brand-navy-900">{{ number_format($pm->amount, 2) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif
    </div>
</section>

@if ($examPeriods->isNotEmpty())
    <section class="jca-card overflow-hidden" aria-labelledby="acct-exam-periods-title">
        <div class="border-b border-border px-5 py-4 sm:px-6">
            <h2 id="acct-exam-periods-title" class="text-lg font-black text-brand-navy-900">Exam periods</h2>
            <p class="mt-0.5 text-sm text-muted">Permit issuance status for {{ $activeYear?->name }}.</p>
        </div>
        <div class="divide-y divide-border">
            @foreach ($examPeriods as $period)
                <div class="flex flex-wrap items-center gap-4 px-5 py-4 sm:px-6">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700">
                        <x-ui.icon name="check-all" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-brand-navy-900">{{ $period->name }}</span>
                        <span class="block text-xs text-muted">
                            {{ $period->starts_on->format('M j') }} &ndash; {{ $period->ends_on->format('M j, Y') }}
                            @if ($period->issue_opens_on && $period->issue_closes_on)
                                <span class="mx-1 text-border">&bull;</span>
                                Issuance: {{ $period->issue_opens_on->format('M j') }} &ndash; {{ $period->issue_closes_on->format('M j') }}
                            @endif
                        </span>
                    </span>
                    <span class="text-sm font-black tabular-nums text-brand-navy-900">{{ $period->issued_count }} issued</span>
                    <x-ui.status-badge :status="$period->status" />
                </div>
            @endforeach
        </div>
    </section>
@endif

<section class="jca-card overflow-hidden" aria-labelledby="acct-recent-payments-title">
    <div class="flex items-center justify-between gap-4 border-b border-border px-5 py-4 sm:px-6">
        <div>
            <h2 id="acct-recent-payments-title" class="text-lg font-black text-brand-navy-900">Recent posted payments</h2>
            <p class="mt-0.5 text-sm text-muted">Last 5 payments posted to the ledger.</p>
        </div>
        <a class="inline-flex items-center gap-2 text-sm font-bold text-brand-navy-700 hover:underline" href="{{ route('billing.index') }}">
            View all <x-ui.icon name="arrow-right" size="size-4" />
        </a>
    </div>
    <div class="divide-y divide-border">
        @forelse ($recentPayments as $payment)
            <div class="flex items-center gap-4 px-5 py-4 sm:px-6">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700">
                    <x-ui.icon name="billing" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-brand-navy-900">
                        {{ $payment->assessment?->enrollment?->student?->last_name }}, {{ $payment->assessment?->enrollment?->student?->first_name }}
                    </span>
                    <span class="block text-xs text-muted">
                        {{ $payment->receipt_reference ?? $payment->payment_reference }}
                        <span class="mx-1 text-border">&bull;</span>
                        {{ str($payment->method)->headline() }}
                        <span class="mx-1 text-border">&bull;</span>
                        {{ $payment->posted_at?->diffForHumans() }}
                    </span>
                </span>
                <span class="text-sm font-black tabular-nums text-brand-navy-900">{{ number_format($payment->amount, 2) }}</span>
            </div>
        @empty
            <div class="p-5 sm:p-6">
                <x-ui.empty-state icon="billing" title="No posted payments" description="Posted payments will appear here as they are recorded." />
            </div>
        @endforelse
    </div>
</section>
