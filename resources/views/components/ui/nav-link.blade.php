@props([
    'href',
    'icon',
    'active' => false,
])

<a href="{{ $href }}"
   @if ($active) aria-current="page" @endif
   {{ $attributes->class([
       'group flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
       'bg-brand-navy-700 text-white shadow-sm' => $active,
       'text-slate-600 hover:bg-brand-blue-100 hover:text-brand-navy-900' => ! $active,
   ]) }}>
    <x-ui.icon :name="$icon" class="{{ $active ? 'text-brand-gold-500' : 'text-slate-400 group-hover:text-brand-navy-700' }}" />
    <span class="min-w-0 flex-1">{{ $slot }}</span>
</a>
