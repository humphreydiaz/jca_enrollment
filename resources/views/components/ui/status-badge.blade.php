@props([
    'tone' => 'neutral',
])

<span {{ $attributes->class(['jca-status', "jca-status-{$tone}"]) }}>
    {{ $slot }}
</span>
