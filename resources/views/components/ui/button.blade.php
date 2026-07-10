@props([
    'type' => 'button',
    'variant' => 'primary',
])

<button type="{{ $type }}" {{ $attributes->class(['jca-button', "jca-button-{$variant}"]) }}>
    {{ $slot }}
</button>
