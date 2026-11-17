
@props(['requirement', 'showUpload' => true])

@php
    $hardLabels = [
        'missing' => 'Missing',
        'submitted' => 'Submitted',
        'verified' => 'Verified',
        'rejected' => 'Replacement Required',
        'waived' => 'Waived by Admin',
    ];

    $status = $requirement->status ?? 'missing';

    $hardSatisfied = in_array(
        $status,
        ['submitted', 'verified', 'waived'],
        true
    );

    $statusLabel = $hardLabels[$status] ?? 'Missing';

    $statusTone = match ($status) {
        'submitted', 'verified', 'waived' => 'verified',
        'rejected' => 'rejected',
        default => 'pending',
    };
@endphp

<article {{ $attributes->class('rounded-xl border border-border bg-white p-4') }}>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h4 class="font-bold text-brand-navy-900">
                {{ $requirement->documentType->name }}
            </h4>

            <p class="mt-1 text-xs text-muted">
                @if($status === 'waived')
                    This requirement has been waived by an authorized Administrator.
                @elseif($hardSatisfied)
                    The Registrar has recorded this requirement as received or verified.
                @elseif($status === 'rejected')
                    Please submit a replacement hard copy to the Registrar.
                @else
                    Submit the required hard copy directly to the Registrar.
                @endif
            </p>
        </div>

        <x-ui.status-badge
            :status="$statusTone"
            :label="$statusLabel"
        />
    </div>

    <div class="mt-4">
        <section
            class="rounded-lg bg-amber-50 p-3"
            aria-label="Hard-copy requirement status"
        >
            <p class="text-xs font-extrabold uppercase tracking-wider text-amber-900">
                Hard-Copy Requirement
            </p>

            <div class="mt-2">
                <x-ui.status-badge
                    :status="$statusTone"
                    :label="$statusLabel"
                />
            </div>

            @if($status === 'waived')
                <p class="mt-2 text-xs text-muted">
                    No additional submission is required unless the Registrar provides further instructions.
                </p>
            @elseif($hardSatisfied)
                <p class="mt-2 text-xs text-muted">
                    This requirement has been recorded by the Registrar.
                </p>
            @elseif($status === 'rejected')
                <p class="mt-2 text-xs text-red-700">
                    A replacement document is required. Please coordinate with the Registrar.
                </p>
            @else
                <p class="mt-2 text-xs text-amber-900">
                    Not yet received. Please submit the physical document to the Registrar.
                </p>
            @endif

            @if($requirement->received_at)
                <p class="mt-2 text-xs text-muted">
                    Received:
                    {{ $requirement->received_at->timezone('Asia/Manila')->format('d M Y, g:i A') }}
                </p>
            @endif
        </section>
    </div>
</article>
