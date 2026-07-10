@props([
    'for',
    'label',
    'hint' => null,
])

<div class="space-y-2">
    <label for="{{ $for }}" class="block text-sm font-bold text-ink">{{ $label }}</label>

    {{ $slot }}

    @if ($hint)
        <p class="text-sm text-muted">{{ $hint }}</p>
    @endif

    @error($for)
        <p class="text-sm font-semibold text-brand-red-600">{{ $message }}</p>
    @enderror
</div>
