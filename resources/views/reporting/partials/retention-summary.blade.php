{{-- D-066 retention summary: counts per outcome for the chosen year (screen and print). --}}
@php($summary = $retentionSummary)
@if ($summary['year'])
    <section class="retention-summary jca-card p-5" aria-labelledby="retention-summary-title">
        <h2 id="retention-summary-title" class="text-lg font-black text-brand-navy-900">Retention — {{ $summary['year']->name }}</h2>
        <p class="mt-1 text-sm text-muted">
            {{ number_format($summary['enrolled']) }} learners enrolled this year.
            @if ($summary['previous'])
                Of the {{ number_format($summary['eligible']) }} learners enrolled in {{ $summary['previous']->name }} who had not graduated,
                {{ number_format($summary['continuing']) }} came back ({{ $summary['retention_rate'] ?? 0 }}% retention){{ $summary['graduated'] ? '; '.number_format($summary['graduated']).' graduated' : '' }}.
            @else
                There is no earlier school year to compare with.
            @endif
        </p>
        <table class="mt-4 w-full max-w-xl text-left text-sm">
            <thead><tr class="border-b border-border"><th scope="col" class="py-1.5">Outcome</th><th scope="col" class="py-1.5 text-right">Learners</th></tr></thead>
            <tbody>
                @foreach (\App\Modules\Reporting\Reports\RetentionReport::OUTCOMES as $key => $label)
                    @continue($key === 'dropped_uncategorised' && $summary['counts'][$key] === 0)
                    <tr class="border-b border-border"><th scope="row" class="py-1.5 font-semibold">{{ $label }}</th><td class="py-1.5 text-right tabular-nums">{{ number_format($summary['counts'][$key]) }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <p class="mt-3 text-xs text-muted">A learner can appear under more than one outcome (for example, repeating and below 75). "Below 75" lists finalized final grades under 75; the school's promotion rule is still pending (P-010).</p>
    </section>
@endif
