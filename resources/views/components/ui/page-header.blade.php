@props([
    'title',
    'description' => null,
    'eyebrow' => null,
])

<header {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-brand-orange-500">{{ $eyebrow }}</p>
        @endif
        <h1 class="{{ $eyebrow ? 'mt-2' : '' }} text-2xl font-black tracking-tight text-brand-navy-900 sm:text-3xl">{{ $title }}</h1>
        @if ($description)
            <p class="mt-2 max-w-3xl text-sm leading-6 text-muted sm:text-base">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
