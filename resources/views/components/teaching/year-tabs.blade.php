@props([
    'years',
    'year' => null,
    'route',
    'label' => 'School years',
])

{{-- One tab per school year the teacher has classes in, newest first. Plain links: works without JavaScript. --}}
@if ($years->isNotEmpty())
    <nav {{ $attributes->class(['flex gap-1 overflow-x-auto rounded-xl border border-border bg-white p-1.5 shadow-sm']) }} aria-label="{{ $label }}">
        @foreach ($years as $option)
            @php($selected = $year?->is($option))
            @php($isCurrent = $option->starts_on->lte(today()) && $option->ends_on->gte(today()))
            <a href="{{ route($route, ['year' => $option->public_id]) }}"
               @if ($selected) aria-current="page" @endif
               class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-lg px-4 py-2 text-sm font-bold transition-colors {{ $selected ? 'bg-brand-navy-700 text-white' : 'text-brand-navy-700 hover:bg-brand-blue-100' }}">
                <span><span class="hidden sm:inline">School Year </span>{{ $option->code }}</span>
                @if ($isCurrent)
                    <span class="rounded-full px-2 py-0.5 text-xs font-extrabold uppercase tracking-wide {{ $selected ? 'bg-brand-gold-500 text-brand-navy-900' : 'bg-brand-blue-100 text-brand-navy-700' }}">Current</span>
                @endif
            </a>
        @endforeach
    </nav>
@endif
