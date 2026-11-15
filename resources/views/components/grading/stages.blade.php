{{-- Shared stage vocabulary for grade approval. Usage: <x-grading.stages :counts="..." :total="..." /> renders a progress bar; with legend shows counts. --}}
@props([
    'counts',
    'total',
    'legend' => true,
    'size' => 'h-2',
])

@php
    $stages = [
        'approved' => ['Approved', 'bg-success-700'],
        'submitted' => ['Waiting for approval', 'bg-brand-navy-700'],
        'correction' => ['Reopen requested', 'bg-brand-red-600'],
        'ready' => ['Complete, not submitted', 'bg-brand-gold-500'],
        'in_progress' => ['Teacher entering', 'bg-brand-orange-500/60'],
        'not_started' => ['Not started', 'bg-border'],
    ];
    $summary = collect($stages)->map(fn ($stage, $key) => ($counts[$key] ?? 0) ? ($counts[$key].' '.mb_strtolower($stage[0])) : null)->filter()->join(', ');
@endphp

<div {{ $attributes }}>
    <div class="flex {{ $size }} overflow-hidden rounded-full bg-canvas" role="img" aria-label="{{ $summary ?: 'No students' }}">
        @foreach ($stages as $key => [$label, $color])
            @if (($counts[$key] ?? 0) > 0 && $total > 0)
                <span class="{{ $color }}" style="width: {{ round($counts[$key] / $total * 100, 2) }}%"></span>
            @endif
        @endforeach
    </div>
    @if ($legend)
        <p class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted" aria-hidden="true">
            @foreach ($stages as $key => [$label, $color])
                @if (($counts[$key] ?? 0) > 0)
                    <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full {{ $color }}"></span><strong class="tabular-nums text-ink">{{ $counts[$key] }}</strong> {{ mb_strtolower($label) }}</span>
                @endif
            @endforeach
        </p>
    @endif
</div>
