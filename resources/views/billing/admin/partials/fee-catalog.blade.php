<section id="fee-catalog" data-section="fee-catalog" class="scroll-mt-28 jca-card overflow-hidden" aria-labelledby="fee-catalog-title">
    <div class="border-b border-border px-5 py-4 sm:px-6"><h2 id="fee-catalog-title" class="text-xl font-black text-brand-navy-900">Fee catalog</h2><p class="mt-1 text-sm text-muted">Reusable fee definitions. Historical assessment snapshots are not changed by catalog edits.</p></div>
    <div class="space-y-5 p-5 sm:p-6">
        <div class="grid gap-4 lg:grid-cols-2">
            @forelse ($feeTypes as $fee)
                <form method="POST" action="{{ route('billing.configuration.fee-types.update', $fee) }}" data-ajax class="rounded-xl border border-border p-4">
                    @csrf
                    @method('PATCH')
                    <div class="flex items-start justify-between gap-3"><div><p class="font-black text-brand-navy-900">{{ $fee->name }}</p><p class="text-xs font-bold uppercase tracking-wide text-muted">{{ $fee->code }}</p></div><x-ui.status-badge :status="$fee->is_active ? 'active' : 'inactive'" /></div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="jca-label">Name<input class="jca-input" name="name" value="{{ $fee->name }}" required></label>
                        <label class="jca-label">Category<input class="jca-input" name="category" value="{{ $fee->category }}" required></label>
                        <label class="jca-label">Maximum quantity<input class="jca-input" type="number" name="max_quantity" min="1" max="100" value="{{ $fee->max_quantity }}" placeholder="Optional"></label>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-4">
                        <label class="jca-check"><input type="checkbox" name="is_mandatory" value="1" @checked($fee->is_mandatory)> Mandatory</label>
                        <label class="jca-check"><input type="checkbox" name="is_conditional" value="1" @checked($fee->is_conditional)> Conditional</label>
                        <label class="jca-check"><input type="checkbox" name="is_permit_critical" value="1" @checked($fee->is_permit_critical)> Permit-critical</label>
                        <label class="jca-check"><input type="checkbox" name="is_active" value="1" @checked($fee->is_active)> Active</label>
                    </div>
                    <button class="jca-button-secondary mt-4" type="submit"><x-ui.icon name="settings" size="size-4" /> Save fee type</button>
                </form>
            @empty
                <x-ui.empty-state class="lg:col-span-2" icon="billing" title="No fee types configured" description="Add the first approved fee definition below." />
            @endforelse
        </div>

        <form method="POST" action="{{ route('billing.configuration.fee-types') }}" data-ajax class="rounded-xl bg-slate-50 p-4 sm:p-5">
            @csrf
            <h3 class="font-black text-brand-navy-900">Add fee type</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <label class="jca-label">Code<input class="jca-input" name="code" value="{{ old('code') }}" required maxlength="20" placeholder="TUITION"></label>
                <label class="jca-label">Name<input class="jca-input" name="name" value="{{ old('name') }}" required placeholder="Tuition fee"></label>
                <label class="jca-label">Category<input class="jca-input" name="category" value="{{ old('category') }}" required placeholder="Academic"></label>
                <label class="jca-label">Maximum quantity<input class="jca-input" type="number" name="max_quantity" min="1" max="100" value="{{ old('max_quantity') }}" placeholder="Optional"></label>
            </div>
            <div class="mt-4 flex flex-wrap gap-4"><label class="jca-check"><input type="checkbox" name="is_mandatory" value="1" @checked(old('is_mandatory'))> Mandatory</label><label class="jca-check"><input type="checkbox" name="is_conditional" value="1" @checked(old('is_conditional'))> Conditional</label><label class="jca-check"><input type="checkbox" name="is_permit_critical" value="1" @checked(old('is_permit_critical'))> Permit-critical</label></div>
            <button class="jca-button-primary mt-4" type="submit"><x-ui.icon name="billing" size="size-4" /> Add fee type</button>
        </form>
    </div>
</section>
