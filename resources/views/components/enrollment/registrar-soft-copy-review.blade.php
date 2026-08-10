@props(['requirement'])

@php
    $labels = [
        'not_uploaded' => 'Not uploaded',
        'submitted' => 'Awaiting review',
        'verified' => 'Verified for admission',
        'rejected' => 'Replacement required',
    ];
    $document = $requirement->activeUpload;
@endphp

<section class="mt-4 rounded-xl border border-brand-blue-100 bg-brand-blue-100/40 p-4" aria-label="Soft copy review">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <p class="text-xs font-extrabold uppercase tracking-wider text-brand-navy-700">Soft copy evidence</p>
            <p class="mt-1 text-xs text-muted">A verified file may be used as the basis for enrollment approval.</p>
        </div>
        <x-ui.status-badge :status="$requirement->soft_copy_status" :label="$labels[$requirement->soft_copy_status] ?? null" />
    </div>

    @if($document)
        <button
            type="button"
            class="jca-button-secondary mt-3 w-full justify-center"
            @click="previewName = @js($requirement->documentType->name); previewUrl = @js(route('requirements.documents.preview', $document)); $refs.softCopyPreview.showModal()"
        >
            <x-ui.icon name="reports" size="size-4" />
            Preview attached soft copy
        </button>
        <p class="mt-2 break-all text-xs text-muted">{{ $document->original_filename }} &middot; {{ number_format($document->byte_size / 1024, 0) }} KB</p>
    @else
        <p class="mt-3 rounded-lg bg-white/70 p-3 text-xs text-muted">No soft copy has been uploaded.</p>
    @endif

    @if(in_array($requirement->soft_copy_status, ['submitted', 'verified'], true) && $document)
        <form method="POST" action="{{ route('requirements.soft-copy.review', $requirement) }}" class="mt-3 grid gap-2 border-t border-brand-blue-100 pt-3 sm:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)_auto] sm:items-end">
            @csrf
            <div>
                <label for="soft_review_status_{{ $requirement->id }}" class="jca-label">Soft-copy decision</label>
                <select id="soft_review_status_{{ $requirement->id }}" name="soft_copy_status" required class="jca-input">
                    <option value="verified">Verify for admission</option>
                    <option value="rejected">Reject / request replacement</option>
                </select>
            </div>
            <div>
                <label for="soft_review_reason_{{ $requirement->id }}" class="jca-label">Reason when rejected</label>
                <input id="soft_review_reason_{{ $requirement->id }}" name="reason" class="jca-input" maxlength="2000" placeholder="Why must this file be replaced?">
            </div>
            <x-ui.button type="submit" variant="secondary">Save review</x-ui.button>
        </form>
    @elseif($requirement->soft_copy_status === 'rejected')
        <p class="mt-3 rounded-lg bg-red-50 p-3 text-xs text-brand-red-600">{{ $requirement->soft_copy_review_reason ?: 'A replacement soft copy is required.' }}</p>
    @endif
</section>
