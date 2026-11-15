@props([
    'status' => null,
    'tone' => null,
    'label' => null,
    'variant' => 'auto',
])

@php
    $statusValue = $status instanceof \BackedEnum ? $status->value : $status;
    $statusKey = $statusValue === null
        ? null
        : str((string) $statusValue)->lower()->replace([' ', '-'], '_')->toString();
    $resolvedTone = $tone ?? match ($statusKey) {
        'active', 'approved', 'authorized', 'clear', 'complete', 'completed', 'confirmed',
        'delivered', 'eligible', 'enrolled', 'granted', 'issued', 'open', 'paid', 'posted',
        'published', 'ready', 'sent', 'success', 'successful', 'verified', 'waived', 'graduated' => 'ready',
        'assessment_pending', 'assessment_required', 'grades_pending', 'awaiting_confirmation', 'draft',
        'incomplete', 'missing', 'more_information_required', 'not_authorized', 'not_selected',
        'not_verified', 'pending', 'planned', 'review_required', 'waitlisted', 'transferred_out' => 'warning',
        'accepted', 'generating', 'processing', 'queued', 'scheduled', 'submitted',
        'under_review' => 'info',
        'blocked', 'cancelled', 'canceled', 'denied', 'expired', 'failed', 'overdue',
        'not_approved', 'rejected', 'reversed', 'revoked', 'dropped' => 'danger',
        default => 'neutral',
    };
    $resolvedLabel = $label ?? ($statusValue === null
        ? null
        : str((string) $statusValue)->replace(['_', '-'], ' ')->headline());
    $resolvedVariant = $variant === 'auto'
        ? ($statusValue === null ? 'soft' : match ($resolvedTone) {
            'ready' => 'outline',
            'neutral' => 'flat',
            default => 'soft',
        })
        : (in_array($variant, ['soft', 'outline', 'flat'], true) ? $variant : 'soft');
@endphp

<span {{ $attributes->class(['jca-status', "jca-status-{$resolvedTone}", "jca-status-{$resolvedVariant}"]) }}>
    <span class="jca-status-dot size-1.5 rounded-full bg-current opacity-70" aria-hidden="true"></span>
    {{ $resolvedLabel ?? $slot }}
</span>
