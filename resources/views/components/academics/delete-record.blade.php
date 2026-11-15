@props([
    'action',
    'label',
    'usage' => [],
    'can' => false,
    'archiveHint' => 'Archive it instead so its history is kept.',
])

@if ($can)
    @if ($usage === [])
        <details {{ $attributes->class(['group/delete rounded-lg border border-red-200 bg-red-50/60']) }}>
            <summary class="cursor-pointer list-none px-3 py-2 text-sm font-bold text-brand-red-600 [&::-webkit-details-marker]:hidden">
                Delete {{ $label }}…
            </summary>
            <form method="POST" action="{{ $action }}" class="space-y-3 border-t border-red-200 px-3 pb-3 pt-3">
                @csrf @method('DELETE')
                <p class="text-sm text-brand-red-600">This permanently deletes {{ $label }}. Use it only for a record that was created by mistake. Nothing else uses it yet.</p>
                <label class="jca-check text-sm">
                    <input type="checkbox" name="confirm_deletion" value="1" required>
                    I understand this cannot be undone.
                </label>
                <x-ui.button type="submit" variant="danger">Delete permanently</x-ui.button>
            </form>
        </details>
    @else
        <p {{ $attributes->class(['text-xs leading-5 text-muted']) }}>
            <span class="font-semibold text-ink">Can't be deleted:</span>
            used by {{ app(\App\Modules\Academics\Support\AcademicRecordUsage::class)->describe($usage) }}. {{ $archiveHint }}
        </p>
    @endif
@endif
