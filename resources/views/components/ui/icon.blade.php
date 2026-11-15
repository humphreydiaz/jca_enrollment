@props([
    'name',
    'size' => 'size-5',
])

<svg {{ $attributes->class([$size, 'shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" />
            @break
        @case('announcement')
            <path d="M4 13V8a2 2 0 0 1 2-2h3l7-3v15l-7-3H6a2 2 0 0 1-2-2Z" /><path d="M9 15l1.5 5H7l-1.2-5" /><path d="M19 8a4 4 0 0 1 0 5" />
            @break
        @case('finance')
            <rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 10h18" /><path d="M7 15h3" />
            @break
        @case('billing')
            <path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z" /><path d="M9 8h6M9 12h6M9 16h3" />
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z" />
            @break
        @case('enrollment')
            <path d="M7 3h10a2 2 0 0 1 2 2v16H5V5a2 2 0 0 1 2-2Z" /><path d="M9 3v4h6V3M8 11h8M8 15h5" />
            @break
        @case('sections')
            <path d="M4 5h6v6H4zM14 5h6v6h-6zM4 15h6v4H4zM14 15h6v4h-6z" />
            @break
        @case('support')
            <path d="M12 21s7-3.5 7-10V5l-7-2-7 2v6c0 6.5 7 10 7 10Z" /><path d="M9 12l2 2 4-5" />
            @break
        @case('academics')
            <path d="m3 10 9-5 9 5-9 5-9-5Z" /><path d="M7 12.5V17c3 2 7 2 10 0v-4.5M21 10v6" />
            @break
        @case('progress')
            <path d="M4 19V5M4 19h16" /><path d="m7 15 4-4 3 2 5-6" />
            @break
        @case('message')
            <path d="M4 5h16v12H8l-4 4V5Z" /><path d="M8 9h8M8 13h5" />
            @break
        @case('teaching')
            <path d="M4 4h16v13H4z" /><path d="M8 21h8M12 17v4M8 8h8M8 12h5" />
            @break
        @case('reports')
            <path d="M5 20V10M12 20V4M19 20v-7" />
            @break
        @case('communications')
            <path d="M5 5h14v10H8l-3 3V5Z" /><path d="M9 9h6M9 12h4" />
            @break
        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8" />
            @break
        @case('people')
            <circle cx="9" cy="8" r="3" /><circle cx="17" cy="9" r="2" /><path d="M3 20v-2a5 5 0 0 1 10 0v2M14 20v-1a4 4 0 0 1 7-2.6" />
            @break
        @case('audit')
            <path d="M12 3a9 9 0 1 1-8.2 5.3" /><path d="M3 3v5h5M12 7v5l3 2" />
            @break
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16" />
            @break
        @case('close')
            <path d="m6 6 12 12M18 6 6 18" />
            @break
        @case('logout')
            <path d="M10 17l5-5-5-5M15 12H3" /><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5" />
            @break
        @case('arrow-right')
            <path d="M5 12h14M13 6l6 6-6 6" />
            @break
        @case('inbox')
            <path d="M4 5h16l2 9v5H2v-5l2-9Z" /><path d="M2 14h5l2 3h6l2-3h5" />
            @break
        @case('warning')
            <path d="M12 3 2.5 20h19L12 3Z" /><path d="M12 9v4M12 17h.01" />
            @break
        @case('search')
            <circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" />
            @break
        @case('check-all')
            <path d="M2 12l5 5L18 6" /><path d="M7 12l5 5L23 6" />
            @break
        @case('check')
            <path d="M5 12l5 5L20 7" />
            @break
        @case('eye')
            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" /><circle cx="12" cy="12" r="2.5" />
            @break
        @case('eye-off')
            <path d="m3 3 18 18" /><path d="M10.6 6.2A9.9 9.9 0 0 1 12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-2.1 2.8M6.6 6.6C4 8.2 2.5 12 2.5 12s3.5 6 9.5 6a9.8 9.8 0 0 0 4.1-.9" /><path d="M10.2 10.2a2.5 2.5 0 0 0 3.6 3.6" />
            @break
        @default
            <circle cx="12" cy="12" r="9" />
    @endswitch
</svg>
