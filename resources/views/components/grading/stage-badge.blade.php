@props(['stage'])

@php
    [$label, $classes] = match ($stage) {
        'approved' => ['Approved', 'bg-success-50 text-success-700'],
        'submitted' => ['Waiting', 'bg-brand-blue-100 text-brand-navy-700'],
        'correction' => ['Reopen requested', 'bg-brand-red-600/10 text-brand-red-600'],
        'ready' => ['Not submitted', 'bg-brand-gold-500/20 text-brand-navy-900'],
        'in_progress' => ['Teacher entering', 'bg-warning-100 text-warning-800'],
        default => ['Not started', 'bg-canvas text-muted'],
    };
@endphp

<span {{ $attributes->class(['inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-extrabold', $classes]) }}>{{ $label }}</span>
