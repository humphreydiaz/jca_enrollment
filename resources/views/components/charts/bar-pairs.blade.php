@props([
    // list of ['label' => string, 'a' => number, 'b' => number|null]
    'rows',
    'seriesA',
    'seriesB' => null,
    'format' => 'count',
    'caption',
])
{{--
    Paired horizontal bars (dataviz: thin bars <=24px, 4px rounded data-end, square at the baseline,
    value at the tip in text ink, legend for two series, hover title, and a table view).
    Plain HTML/CSS so it prints and needs no chart library.
--}}
@php
    $rows = collect($rows);
    $max = max(1, $rows->max(fn ($row) => max((float) $row['a'], (float) ($row['b'] ?? 0))));
    $fmt = fn ($value) => $value === null ? '—' : ($format === 'peso' ? 'PHP '.number_format((float) $value, 0) : number_format((float) $value));
    $width = fn ($value) => $value ? max(0.6, round((float) $value / $max * 100, 2)) : 0;
@endphp
<figure {{ $attributes->class(['jca-chart']) }}>
    <figcaption class="text-sm font-black text-brand-navy-900">{{ $caption }}</figcaption>
    @if ($seriesB)
        <div class="mt-2 flex flex-wrap gap-4 text-xs text-ink" aria-hidden="true">
            <span class="inline-flex items-center gap-1.5"><span class="jca-chart__swatch jca-chart__swatch--a"></span>{{ $seriesA }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="jca-chart__swatch jca-chart__swatch--b"></span>{{ $seriesB }}</span>
        </div>
    @endif
    <div class="mt-3 space-y-3" aria-hidden="true">
        @foreach ($rows as $row)
            <div class="grid grid-cols-[5.5rem_1fr] items-center gap-3 sm:grid-cols-[7rem_1fr]">
                <span class="truncate text-xs font-semibold text-muted" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                <div class="space-y-1">
                    <div class="flex items-center gap-2" title="{{ $row['label'] }} · {{ $seriesA }}: {{ $fmt($row['a']) }}">
                        <span class="block min-w-0 flex-1"><span class="jca-chart__bar jca-chart__bar--a" style="width: {{ $width($row['a']) }}%"></span></span>
                        <span class="w-24 shrink-0 text-xs tabular-nums text-ink">{{ $fmt($row['a']) }}</span>
                    </div>
                    @if ($seriesB)
                        <div class="flex items-center gap-2" title="{{ $row['label'] }} · {{ $seriesB }}: {{ $fmt($row['b']) }}">
                            <span class="block min-w-0 flex-1"><span class="jca-chart__bar jca-chart__bar--b" style="width: {{ $width($row['b']) }}%"></span></span>
                            <span class="w-24 shrink-0 text-xs tabular-nums text-muted">{{ $fmt($row['b']) }}</span>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    <details class="mt-3 text-xs print:hidden">
        <summary class="cursor-pointer font-bold text-brand-navy-700">Show as a table</summary>
        <table class="mt-2 w-full text-left">
            <caption class="sr-only">{{ $caption }}</caption>
            <thead><tr class="border-b border-border"><th scope="col" class="py-1"></th><th scope="col" class="py-1 text-right">{{ $seriesA }}</th>@if ($seriesB)<th scope="col" class="py-1 text-right">{{ $seriesB }}</th>@endif</tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-border"><th scope="row" class="py-1 font-semibold">{{ $row['label'] }}</th><td class="py-1 text-right tabular-nums">{{ $fmt($row['a']) }}</td>@if ($seriesB)<td class="py-1 text-right tabular-nums">{{ $fmt($row['b']) }}</td>@endif</tr>
                @endforeach
            </tbody>
        </table>
    </details>
    {{-- Screen readers get the same numbers as a list, since the bars are decorative. --}}
    <ul class="sr-only">
        @foreach ($rows as $row)
            <li>{{ $row['label'] }}: {{ $seriesA }} {{ $fmt($row['a']) }}{{ $seriesB ? ', '.$seriesB.' '.$fmt($row['b']) : '' }}</li>
        @endforeach
    </ul>
</figure>
