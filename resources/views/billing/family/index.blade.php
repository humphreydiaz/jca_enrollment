<x-layouts.app title="My finances">
    @php($peso = fn ($value) => 'PHP '.number_format((float) $value, 2))
    <div class="space-y-6">
        <header class="jca-card p-6 sm:p-8">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-orange-500">{{ $isGuardian ? 'My children' : 'My school records' }}</p>
            <h1 class="mt-2 text-3xl font-black text-brand-navy-900">Financial summary</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-muted">What was assessed, what has been paid, and what is still due{{ $isGuardian ? ' for each learner linked to your account' : '' }}. Payments appear here after the Accounting office posts them and issues the official receipt. For questions, visit the Accounting office.</p>
        </header>

        @forelse($summaries as $summary)
            @php($id = $summary['assessment']->public_id)
            <section class="jca-card overflow-hidden" aria-labelledby="finance-{{ $id }}">
                <div class="border-b border-border p-5 sm:p-6">
                    <h2 id="finance-{{ $id }}" class="text-xl font-black text-brand-navy-900">{{ $summary['learner'] }}</h2>
                    <p class="text-sm text-muted">{{ $summary['school_year'] }} · {{ $summary['grade'] }}@if($summary['plan']) · {{ str($summary['plan'])->headline() }} payment plan @endif</p>

                    <dl class="mt-5 grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
                        <div class="rounded-xl bg-canvas p-4"><dt class="text-xs font-bold uppercase tracking-wider text-muted">Total fees</dt><dd class="mt-1 text-xl font-black tabular-nums text-brand-navy-900">{{ $peso($summary['net']) }}</dd>@if((float) $summary['discounts'] > 0)<p class="mt-1 text-xs text-muted">After {{ $peso($summary['discounts']) }} discount</p>@endif</div>
                        <div class="rounded-xl bg-canvas p-4"><dt class="text-xs font-bold uppercase tracking-wider text-muted">Paid so far</dt><dd class="mt-1 text-xl font-black tabular-nums text-success-700">{{ $peso($summary['paid']) }}</dd></div>
                        <div class="rounded-xl bg-canvas p-4"><dt class="text-xs font-bold uppercase tracking-wider text-muted">Remaining balance</dt><dd class="mt-1 text-xl font-black tabular-nums {{ (float) $summary['balance'] > 0 ? 'text-brand-red-600' : 'text-success-700' }}">{{ $peso($summary['balance']) }}</dd>@if((float) $summary['credit'] > 0)<p class="mt-1 text-xs text-muted">Credit on file: {{ $peso($summary['credit']) }}</p>@endif</div>
                        <div class="rounded-xl bg-canvas p-4">
                            <dt class="text-xs font-bold uppercase tracking-wider text-muted">Next payment</dt>
                            @if($summary['next_due'])
                                <dd class="mt-1 text-xl font-black tabular-nums text-brand-navy-900">{{ $peso($summary['next_due']['unpaid']) }}</dd>
                                <p class="mt-1 text-xs {{ $summary['next_due']['status'] === 'overdue' ? 'font-bold text-brand-red-600' : 'text-muted' }}">{{ $summary['next_due']['status'] === 'overdue' ? 'Overdue since' : 'Due' }} {{ $summary['next_due']['due_on']?->format('d M Y') ?? 'date to be set' }}</p>
                            @elseif((float) $summary['balance'] <= 0)
                                <dd class="mt-1 text-xl font-black text-success-700">Fully paid</dd>
                            @else
                                <dd class="mt-1 text-sm font-bold text-brand-navy-900">Ask the Accounting office for your payment schedule.</dd>
                            @endif
                        </div>
                    </dl>
                    @if($summary['overdue'] > 0)
                        <p class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm font-semibold text-brand-red-600" role="status">{{ $peso($summary['overdue']) }} is past its due date. Please settle it at the Accounting office.</p>
                    @endif
                </div>

                <div class="grid gap-6 p-5 sm:p-6 2xl:grid-cols-2">
                    <div>
                        <h3 class="font-black text-brand-navy-900">Fee breakdown</h3>
                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-brand-blue-50 text-brand-navy-900"><tr><th scope="col" class="p-2">Fee</th><th scope="col" class="p-2 text-right">Amount</th><th scope="col" class="p-2 text-right">Discount</th><th scope="col" class="p-2 text-right">To pay</th></tr></thead>
                                <tbody class="divide-y divide-border">
                                    @foreach($summary['items'] as $item)
                                        <tr><td class="p-2">{{ $item->fee_name }}</td><td class="p-2 text-right tabular-nums">{{ number_format((float) $item->gross_amount, 2) }}</td><td class="p-2 text-right tabular-nums">{{ (float) $item->discount_amount > 0 ? number_format((float) $item->discount_amount, 2) : '—' }}</td><td class="p-2 text-right tabular-nums">{{ number_format((float) $item->net_amount, 2) }}</td></tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t-2 border-brand-navy-900 font-black text-brand-navy-900"><tr><td class="p-2">Total</td><td class="p-2 text-right tabular-nums">{{ number_format((float) $summary['gross'], 2) }}</td><td class="p-2 text-right tabular-nums">{{ number_format((float) $summary['discounts'], 2) }}</td><td class="p-2 text-right tabular-nums">{{ number_format((float) $summary['net'], 2) }}</td></tr></tfoot>
                            </table>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-black text-brand-navy-900">Payment schedule</h3>
                        @if($summary['schedule']->isEmpty())
                            <p class="mt-3 text-sm text-muted">No payment plan has been set yet. The Accounting office sets it with you.</p>
                        @else
                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-brand-blue-50 text-brand-navy-900"><tr><th scope="col" class="p-2">Installment</th><th scope="col" class="p-2">Due</th><th scope="col" class="p-2 text-right">Amount</th><th scope="col" class="p-2">Status</th></tr></thead>
                                    <tbody class="divide-y divide-border">
                                        @foreach($summary['schedule'] as $row)
                                            <tr><td class="p-2">{{ $row['label'] }}</td><td class="p-2 whitespace-nowrap">{{ $row['due_on']?->format('d M Y') ?? '—' }}</td><td class="p-2 text-right tabular-nums">{{ number_format((float) $row['amount'], 2) }}</td><td class="p-2"><x-ui.status-badge :status="$row['status']" :label="match ($row['status']) { 'partly_paid' => 'Partly paid · '.number_format((float) $row['unpaid'], 2).' left', 'overdue' => 'Overdue · '.number_format((float) $row['unpaid'], 2).' left', default => null }" /></td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="2xl:col-span-2">
                        <h3 class="font-black text-brand-navy-900">Payments received</h3>
                        @if($summary['payments']->isEmpty())
                            <p class="mt-3 text-sm text-muted">No posted payments yet.</p>
                        @else
                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-brand-blue-50 text-brand-navy-900"><tr><th scope="col" class="p-2">Date received</th><th scope="col" class="p-2">Official receipt</th><th scope="col" class="p-2">Method</th><th scope="col" class="p-2 text-right">Amount</th></tr></thead>
                                    <tbody class="divide-y divide-border">
                                        @foreach($summary['payments'] as $payment)
                                            <tr><td class="p-2 whitespace-nowrap">{{ $payment->received_on?->format('d M Y') }}</td><td class="p-2 font-mono">{{ $payment->receipt_reference }}</td><td class="p-2">{{ str($payment->method)->headline() }}</td><td class="p-2 text-right tabular-nums">{{ number_format((float) $payment->amount, 2) }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @empty
            <x-ui.empty-state icon="billing" title="No financial record yet" description="A summary appears here once the Accounting office finalizes the assessment{{ $isGuardian ? ' for a learner linked to your account' : '' }}." />
        @endforelse
    </div>
</x-layouts.app>
