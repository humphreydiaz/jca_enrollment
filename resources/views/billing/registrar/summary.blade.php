<x-layouts.app title="Financial status">
    <section class="jca-card p-6">
        <h1 class="text-3xl font-black text-brand-navy-900">Financial status</h1>
        <p class="mt-2">{{ $assessment->enrollment->student->full_name }} · {{ $assessment->assessment_reference }}</p>
        <dl class="mt-6 grid gap-4 sm:grid-cols-3">
            <div><dt>Assessed total</dt><dd class="font-bold">PHP {{ number_format((float) $assessment->net_total, 2) }}</dd></div>
            <div><dt>Total paid</dt><dd class="font-bold">PHP {{ number_format((float) $balance['paid'], 2) }}</dd></div>
            <div><dt>Current balance</dt><dd class="font-bold">PHP {{ number_format((float) $balance['due'], 2) }}</dd></div>
            <div><dt>Plan status</dt><dd class="mt-1"><x-ui.status-badge :status="$assessment->plan ? $assessment->plan->plan_type : 'not_selected'" :tone="$assessment->plan ? 'info' : null" /></dd></div>
            <div><dt>Exam authorization</dt><dd class="mt-1"><x-ui.status-badge :status="$permitStatus === 'issued' ? 'authorized' : 'not_authorized'" /></dd></div>
        </dl>
        @if($issuedPermit)<a class="jca-button-secondary mt-5 inline-flex" href="{{ route('billing.print.permit',$issuedPermit) }}">Print issued permit</a>@endif
    </section>
</x-layouts.app>
