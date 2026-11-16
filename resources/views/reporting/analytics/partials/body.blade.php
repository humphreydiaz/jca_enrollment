@php
    $i = $insights;
    $f = $i['finance'];
    $fl = $i['finance_last'];
    $prevName = $i['previous']?->name;
    $peso = fn ($value) => 'PHP '.number_format((float) $value, 2);
    // "up 12% from 214" / "down 3% from PHP 10,000.00" / "same as"
    $change = function ($now, $before, bool $money = false) {
        if ($before === null) {
            return null;
        }
        $shown = $money ? 'PHP '.number_format((float) $before, 2) : number_format((float) $before);
        if ((float) $before == 0.0) {
            return (float) $now > 0 ? 'new this year (none last year)' : 'none last year either';
        }
        $pct = round(((float) $now - (float) $before) / (float) $before * 100, 1);

        return match (true) {
            $pct > 0 => 'up '.$pct.'% from '.$shown,
            $pct < 0 => 'down '.abs($pct).'% from '.$shown,
            default => 'same as '.$shown,
        };
    };
    $direction = fn ($now, $before) => $before === null || (float) $now == (float) $before ? 'flat' : ((float) $now > (float) $before ? 'up' : 'down');
    $grades = collect($i['demographics']['stages'])->flatten(1);
    $male = $grades->sum('male');
    $female = $grades->sum('female');
@endphp

{{-- 1. What the owner wants to know first, in sentences. --}}
<section class="jca-card p-5 sm:p-6" aria-labelledby="insight-headlines">
    <h2 id="insight-headlines" class="text-lg font-black text-brand-navy-900">At a glance — {{ $i['year']->name }}</h2>
    <ul class="mt-3 list-disc space-y-1.5 pl-5 text-sm leading-6 text-ink">
        <li><strong>Enrollment:</strong> {{ number_format($i['enrolled']) }} learners enrolled{{ $prevName ? ', '.$change($i['enrolled'], $i['enrolled_last']).' in '.$prevName : '' }}.</li>
        <li><strong>Collections:</strong> {{ $peso($f['collected']) }} collected of {{ $peso($f['assessed']) }} assessed ({{ $f['rate'] ?? 0 }}%){{ $fl ? '; collections are '.$change($f['collected'], $fl['collected'], true).' last year' : '' }}.</li>
        <li><strong>Still to collect:</strong> {{ $peso($f['uncollected']) }} across {{ number_format($f['assessments']) }} finalized assessments.</li>
        <li><strong>This month:</strong> {{ $peso($i['this_month']) }} collected so far in {{ $i['today']->format('F Y') }}, {{ $change($i['this_month'], $i['last_month'], true) ?? '' }} in {{ $i['today']->subMonthNoOverflow()->format('F') }}.</li>
        @unless($i['projection']['year_over'])
            <li><strong>Outlook:</strong> at the pace of the last three months the school would collect about {{ $peso($i['projection']['at_pace']) }} by the end of the school year{{ $i['projection']['gap_at_pace'] > 0 ? ', leaving about '.$peso($i['projection']['gap_at_pace']).' uncollected' : ', which covers everything assessed' }}.</li>
        @endunless
    </ul>
</section>

{{-- 2. Stat tiles. --}}
<section aria-label="Key figures" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
    @foreach ([
        ['Enrolled learners', number_format($i['enrolled']), $change($i['enrolled'], $i['enrolled_last']), $direction($i['enrolled'], $i['enrolled_last'])],
        ['Fees assessed', $peso($f['assessed']), $fl ? $change($f['assessed'], $fl['assessed'], true) : null, $fl ? $direction($f['assessed'], $fl['assessed']) : 'flat'],
        ['Collected', $peso($f['collected']), ($f['rate'] ?? 0).'% of assessed'.($fl ? ' · '.$change($f['collected'], $fl['collected'], true) : ''), $fl ? $direction($f['collected'], $fl['collected']) : 'flat'],
        ['Uncollected', $peso($f['uncollected']), $fl ? $change($f['uncollected'], $fl['uncollected'], true) : null, 'flat'],
        ['Collected this month', $peso($i['this_month']), $change($i['this_month'], $i['last_month'], true).' last month', $direction($i['this_month'], $i['last_month'])],
    ] as [$label, $value, $note, $trend])
        <div class="jca-card p-5">
            <p class="text-xs font-bold uppercase tracking-wider text-muted">{{ $label }}</p>
            <p class="mt-2 text-2xl font-black tabular-nums text-brand-navy-900">{{ $value }}</p>
            @if ($note)
                <p class="mt-1 text-xs text-muted">
                    @if ($trend === 'up')<span aria-hidden="true">▲</span>@elseif ($trend === 'down')<span aria-hidden="true">▼</span>@endif
                    {{ $note }}
                </p>
            @endif
        </div>
    @endforeach
</section>

{{-- 3-4. Collections over time. --}}
<section class="grid gap-6 xl:grid-cols-2">
    <div class="jca-card p-5 sm:p-6">
        <x-charts.bar-pairs
            caption="Collections by month: {{ $i['year']->name }} vs the same month last year"
            :rows="collect($i['months'])->map(fn ($label, $key) => ['label' => $label, 'a' => $i['monthly'][$key], 'b' => $prevName ? $i['monthly_last'][$key] : null])->values()"
            series-a="This school year"
            :series-b="$prevName ? 'Same month, '.$prevName : null"
            format="peso" />
    </div>
    <div class="jca-card p-5 sm:p-6">
        <x-charts.bar-pairs
            caption="Due on the payment plans vs actually collected, per month"
            :rows="collect($i['months'])->map(fn ($label, $key) => ['label' => $label, 'a' => $i['monthly'][$key], 'b' => $i['installments'][$key]])->values()"
            series-a="Collected"
            series-b="Due on payment plans"
            format="peso" />
        <p class="mt-3 text-xs leading-5 text-muted">Months where the orange bar is longer than the blue one collected less than the families' payment plans scheduled.</p>
    </div>
</section>

{{-- 5. Outlook (plain estimates, clearly labelled). --}}
@unless($i['projection']['year_over'])
    <section class="jca-card p-5 sm:p-6" aria-labelledby="insight-outlook">
        <h2 id="insight-outlook" class="text-lg font-black text-brand-navy-900">End-of-year outlook (estimates)</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl bg-canvas p-4"><dt class="text-xs font-bold uppercase tracking-wider text-muted">If every remaining installment is paid on time</dt><dd class="mt-1 text-xl font-black tabular-nums text-brand-navy-900">{{ $peso($i['projection']['on_schedule']) }}</dd></div>
            <div class="rounded-xl bg-canvas p-4"><dt class="text-xs font-bold uppercase tracking-wider text-muted">At the last three months' pace ({{ $peso($i['projection']['monthly_pace']) }}/month, {{ $i['projection']['months_left'] }} months left)</dt><dd class="mt-1 text-xl font-black tabular-nums text-brand-navy-900">{{ $peso($i['projection']['at_pace']) }}</dd></div>
            <div class="rounded-xl bg-canvas p-4"><dt class="text-xs font-bold uppercase tracking-wider text-muted">Likely still uncollected at year end</dt><dd class="mt-1 text-xl font-black tabular-nums {{ $i['projection']['gap_at_pace'] > 0 ? 'text-brand-red-600' : 'text-success-700' }}">{{ $peso($i['projection']['gap_at_pace']) }}</dd></div>
        </dl>
        <p class="mt-3 text-xs leading-5 text-muted">Simple projections from posted payments and the families' payment plans, capped at what was assessed. They are planning aids, not promises.</p>
    </section>
@endunless

{{-- 6-7. Enrollment and demographics. --}}
<section class="grid gap-6 xl:grid-cols-2">
    <div class="jca-card p-5 sm:p-6">
        <x-charts.bar-pairs
            caption="Enrolled learners per grade level"
            :rows="$i['by_grade']->map(fn ($row) => ['label' => $row['grade'], 'a' => $row['current'], 'b' => $prevName ? $row['previous'] : null])"
            series-a="{{ $i['year']->name }}"
            :series-b="$prevName"
            format="count" />
    </div>
    <div class="jca-card p-5 sm:p-6">
        <x-charts.bar-pairs
            caption="Learners by sex per grade level ({{ $i['year']->name }})"
            :rows="$grades->map(fn ($row) => ['label' => $row['grade'], 'a' => $row['male'], 'b' => $row['female']])->values()"
            series-a="Male ({{ number_format($male) }})"
            series-b="Female ({{ number_format($female) }})"
            format="count" />
        <h3 class="mt-5 text-sm font-black text-brand-navy-900">By learner category</h3>
        <ul class="mt-2 grid gap-2 text-sm sm:grid-cols-3">
            @forelse ($i['by_category'] as $category => $count)
                <li class="rounded-lg bg-canvas px-3 py-2"><span class="block text-xs text-muted">{{ str($category)->headline() }}</span><span class="font-black tabular-nums text-brand-navy-900">{{ number_format($count) }}</span></li>
            @empty
                <li class="text-muted">No enrolled learners yet.</li>
            @endforelse
        </ul>
    </div>
</section>
