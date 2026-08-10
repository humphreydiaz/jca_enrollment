@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
])

<div {{ $attributes->class(['rounded-xl border border-dashed border-slate-300 bg-slate-50/80 px-6 py-10 text-center']) }}>
    <span class="mx-auto grid size-11 place-items-center rounded-xl bg-white text-slate-400 shadow-sm ring-1 ring-slate-200">
        <x-ui.icon :name="$icon" />
    </span>
    <h3 class="mt-4 font-bold text-brand-navy-900">{{ $title }}</h3>
    @if ($description)
        <p class="mx-auto mt-1 max-w-lg text-sm leading-6 text-muted">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-5">{{ $action }}</div>
    @endisset
</div>
