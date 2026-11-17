@props([
    'active' => 'schedules',
    'year' => null,
    'description' => null,
])

@php
    $yearQuery = $year ? ['year' => $year->public_id] : [];
    $tabs = [
        'schedules' => ['label' => 'Class schedules', 'route' => route('academics.admin.index', $yearQuery), 'icon' => 'teaching'],
        'curriculum' => ['label' => 'Curriculum', 'route' => route('academics.admin.curricula.index', $yearQuery), 'icon' => 'academics'],
        'setup' => ['label' => 'Setup', 'route' => route('academics.admin.setup', $yearQuery), 'icon' => 'settings'],
    ];
@endphp

<div class="space-y-4">
    <x-ui.page-header
        title="Academics"
        eyebrow="School records"
        :description="$description ?? 'Manage one school year at a time: its class program, sections, subjects, class times, and teachers.'"
    />

    <nav class="flex gap-1 overflow-x-auto rounded-xl border border-border bg-white p-1.5 shadow-sm" aria-label="Academics sections">
        @foreach ($tabs as $key => $tab)
            <a href="{{ $tab['route'] }}"
               @if ($active === $key) aria-current="page" @endif
               class="inline-flex items-center gap-2 whitespace-nowrap rounded-lg px-4 py-2 text-sm font-bold {{ $active === $key ? 'bg-brand-navy-700 text-white' : 'text-brand-navy-700 hover:bg-brand-blue-100' }}">
                <x-ui.icon :name="$tab['icon']" size="size-4" />
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
