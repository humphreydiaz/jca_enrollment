@props(['requirement', 'showUpload' => true])

@php
    $softLabels = [
        'not_uploaded' => 'Not uploaded',
        'submitted' => 'Submitted',
        'verified' => 'Verified for admission',
        'rejected' => 'Replacement required',
    ];
    $hardLabels = [
        'missing' => 'Not Received',
        'submitted' => 'Hard Copy Received',
        'verified' => 'Verified hard copy',
        'rejected' => 'Replacement required',
        'waived' => 'Waived by Admin',
    ];
    $softSatisfied = $requirement->soft_copy_status === 'verified';
    $softCopyFilename = $requirement->activeUpload?->original_filename;
    $hardSatisfied = in_array($requirement->status, ['verified', 'waived'], true)
        || ($requirement->status === 'submitted' && $requirement->received_at !== null);
    $hardOutstanding = ! $hardSatisfied;
    $canUpload = $showUpload && auth()->check() && auth()->user()->can('upload', $requirement);
@endphp

<article {{ $attributes->class('rounded-xl border border-border bg-white p-4') }}>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h4 class="font-bold text-brand-navy-900">{{ $requirement->documentType->name }}</h4>
            <p class="mt-1 text-xs text-muted">
                @if($softSatisfied || $hardSatisfied)
                    Accepted as enrollment evidence.
                @else
                    A verified soft copy or received hard copy is needed for approval.
                @endif
            </p>
        </div>
        <x-ui.status-badge
            :status="($softSatisfied || $hardSatisfied) ? 'verified' : 'pending'"
            :label="($softSatisfied || $hardSatisfied) ? 'Admission evidence complete' : 'Evidence needed'"
        />
    </div>

    <div class="mt-4 grid gap-3 md:grid-cols-2">
        <section class="rounded-lg bg-brand-blue-100/60 p-3" aria-label="Soft copy status">
            <p class="text-xs font-extrabold uppercase tracking-wider text-brand-navy-700">Soft copy evidence</p>
            <div class="mt-2"><x-ui.status-badge :status="$requirement->soft_copy_status" :label="$softLabels[$requirement->soft_copy_status] ?? null" /></div>
            @if($requirement->soft_copy_status === 'rejected' && $requirement->soft_copy_review_reason)
                <p class="mt-2 text-xs text-brand-red-600">{{ $requirement->soft_copy_review_reason }}</p>
            @elseif($softSatisfied)
                <p class="mt-2 text-xs text-muted">The Registrar accepted this file as enrollment evidence.</p>
            @elseif($requirement->soft_copy_status === 'submitted')
                <p class="mt-2 text-xs font-semibold text-success-700">&check; Soft copy submitted successfully{{ $softCopyFilename ? ' — '.$softCopyFilename : '' }}. The Registrar will review it.</p>
            @else
                <p class="mt-2 text-xs text-muted">PDF, JPG, or PNG, up to 5 MB.</p>
            @endif

            @if($canUpload)
                <form method="POST" action="{{ route('requirements.documents.store', $requirement) }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                    @csrf
                    <label for="soft_copy_{{ $requirement->id }}" class="jca-label">{{ $requirement->activeUpload ? 'Replace soft copy' : 'Upload soft copy' }}</label>
                    <input id="soft_copy_{{ $requirement->id }}" name="document" type="file" required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="block w-full rounded-lg border border-border bg-white p-2 text-xs text-ink file:mr-3 file:rounded-md file:border-0 file:bg-brand-blue-100 file:px-3 file:py-2 file:text-xs file:font-bold file:text-brand-navy-700">
                    <x-ui.button type="submit" variant="secondary" class="w-full justify-center">Submit privately</x-ui.button>
                </form>
            @endif
        </section>

        <section class="rounded-lg bg-amber-50 p-3" aria-label="Hard copy status">
            <p class="text-xs font-extrabold uppercase tracking-wider text-amber-900">Hard-copy obligation</p>
            <div class="mt-2"><x-ui.status-badge :status="$requirement->status" :label="$hardLabels[$requirement->status] ?? null" /></div>
            @if($hardOutstanding)
                <p class="mt-2 text-xs text-amber-900">Still required. Submit the hard copy to the Registrar when available, even if the soft copy is verified.</p>
            @elseif($requirement->status === 'waived')
                <p class="mt-2 text-xs text-muted">An authorized exception was recorded.</p>
            @else
                <p class="mt-2 text-xs text-muted">The Registrar recorded receipt of the physical document.</p>
            @endif
        </section>
    </div>
</article>
