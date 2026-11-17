@props(['lines'])

{{-- D-059: what an audit entry changed. Values are recorded masked; unrecorded personal fields are named only. --}}
@if ($lines !== [])
    <ul {{ $attributes->merge(['class' => 'space-y-1 text-sm']) }}>
        @foreach ($lines as $line)
            <li class="break-words">
                <span class="font-bold text-ink">{{ $line['field'] }}:</span>
                @if ($line['recorded'])
                    <span class="text-muted">{{ $line['old'] ?? '—' }}</span> → <span class="font-semibold text-ink">{{ $line['new'] ?? '—' }}</span>
                @else
                    <span class="text-muted">changed (value not kept in the audit log)</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
