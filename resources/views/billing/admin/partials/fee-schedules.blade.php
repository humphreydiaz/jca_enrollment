<section id="fee-schedules" data-section="fee-schedules" class="scroll-mt-28 jca-card overflow-hidden" aria-labelledby="fee-schedules-title">
    <div class="border-b border-border px-5 py-4 sm:px-6"><h2 id="fee-schedules-title" class="text-xl font-black text-brand-navy-900">Fee schedule versions</h2><p class="mt-1 text-sm text-muted">Build a draft, add fee items, then publish the immutable version used for new assessments.</p></div>
    <div class="space-y-5 p-5 sm:p-6">
        @forelse ($schedules as $schedule)
            <article class="rounded-xl border border-border p-4">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-black text-brand-navy-900">{{ $schedule->name }}</h3><p class="mt-1 text-sm text-muted">Version {{ $schedule->version }} · {{ $schedule->items->count() }} {{ \Illuminate\Support\Str::plural('item', $schedule->items->count()) }}</p></div><x-ui.status-badge :status="$schedule->status" /></div>
                @if ($schedule->items->isNotEmpty())
                    @php $isDraft = $schedule->status === 'draft'; @endphp
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="text-xs uppercase text-slate-500">
                                <tr><th class="py-2 pr-4">Fee</th><th class="py-2 pr-4">Description</th><th class="py-2 text-right">Amount</th>@if ($isDraft)<th class="py-2 pl-4 text-right">Action</th>@endif</tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($schedule->items as $item)
                                    <tr>
                                        <td class="py-2 pr-4 font-semibold">{{ $item->feeType->name }}</td>
                                        <td class="py-2 pr-4 text-muted">{{ $item->description }}</td>
                                        <td class="py-2 text-right tabular-nums">PHP {{ number_format((float) $item->amount, 2) }}</td>
                                        @if ($isDraft)
                                            <td class="py-2 pl-4 text-right">
                                                <div class="flex flex-col items-end gap-2">
                                                    <details class="text-left">
                                                        <summary class="jca-button-secondary cursor-pointer list-none">Edit</summary>
                                                        <form method="POST" action="{{ route('billing.configuration.schedule-items.update', [$schedule, $item]) }}" data-ajax class="mt-2 w-72 rounded-xl border border-border bg-white p-3 text-left shadow-lg">
                                                            @csrf @method('PUT')
                                                            <label class="jca-label">Description<input class="jca-input" name="description" value="{{ $item->description }}" maxlength="255" required></label>
                                                            <label class="jca-label mt-2">Amount<input class="jca-input" name="amount" inputmode="decimal" value="{{ $item->amount }}" required></label>
                                                            <div class="mt-2 flex flex-col gap-2">
                                                                <label class="jca-check"><input type="checkbox" name="is_required" value="1" @checked($item->is_required)> Required</label>
                                                                <label class="jca-check"><input type="checkbox" name="is_default_selected" value="1" @checked($item->is_default_selected)> Selected by default</label>
                                                            </div>
                                                            <button class="jca-button-primary mt-3 w-full" type="submit">Save changes</button>
                                                        </form>
                                                    </details>
                                                    <details class="text-left">
                                                        <summary class="jca-button-danger cursor-pointer list-none">Remove</summary>
                                                        <form method="POST" action="{{ route('billing.configuration.schedule-items.destroy', [$schedule, $item]) }}" data-ajax class="mt-2 w-72 rounded-xl border border-border bg-white p-3 text-left shadow-lg">
                                                            @csrf @method('DELETE')
                                                            <p class="text-sm font-semibold text-brand-navy-900">Remove {{ $item->feeType->name }}?</p>
                                                            <p class="mt-1 text-xs text-muted">This only affects the draft. It cannot be undone.</p>
                                                            <button class="jca-button-danger mt-3 w-full" type="submit">Confirm remove</button>
                                                        </form>
                                                    </details>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if ($schedule->status === 'draft')
                    <form method="POST" action="{{ route('billing.configuration.schedule-items', $schedule) }}" data-ajax class="mt-4 grid gap-3 rounded-xl bg-slate-50 p-4 md:grid-cols-2 xl:grid-cols-4">
                        @csrf
                        <label class="jca-label">Fee type<select class="jca-input" name="fee_type_id" required>@foreach ($feeTypes as $fee)<option value="{{ $fee->id }}">{{ $fee->code }} — {{ $fee->name }}</option>@endforeach</select></label>
                        <label class="jca-label">Description<input class="jca-input" name="description" required></label>
                        <label class="jca-label">Amount<input class="jca-input" name="amount" inputmode="decimal" placeholder="0.00" required></label>
                        <div class="flex flex-col justify-end gap-2"><label class="jca-check"><input type="checkbox" name="is_required" value="1"> Required</label><label class="jca-check"><input type="checkbox" name="is_default_selected" value="1"> Selected by default</label></div>
                        <button class="jca-button-secondary md:col-span-2 xl:col-span-4" type="submit">Add schedule item</button>
                    </form>
                    @if ($schedule->items->isNotEmpty())
                        <form method="POST" action="{{ route('billing.configuration.schedule-publish', $schedule) }}" data-ajax class="mt-3">@csrf<button class="jca-button-primary" type="submit"><x-ui.icon name="support" size="size-4" /> Publish and lock version</button></form>
                    @endif
                @endif
            </article>
        @empty
            <x-ui.empty-state icon="finance" title="No fee schedules" description="Create a draft schedule below, then add its fee items." />
        @endforelse

        <form method="POST" action="{{ route('billing.configuration.schedules') }}" data-ajax class="rounded-xl bg-slate-50 p-4 sm:p-5">
            @csrf
            <h3 class="font-black text-brand-navy-900">Create draft schedule</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="jca-label">School year<select class="jca-input" name="academic_year_id" required>@foreach ($academicYears as $year)<option value="{{ $year->id }}">{{ $year->name }}</option>@endforeach</select></label>
                <label class="jca-label">Grade<select class="jca-input" name="grade_level_id"><option value="">All grades</option>@foreach ($gradeLevels as $grade)<option value="{{ $grade->id }}">{{ $grade->name }}</option>@endforeach</select></label>
                <label class="jca-label">Schedule name<input class="jca-input" name="name" value="{{ old('name') }}" required></label>
                <label class="jca-label">Version<input class="jca-input" type="number" name="version" min="1" value="{{ old('version', 1) }}" required></label>
                <label class="jca-label">Effective date<input class="jca-input" type="date" name="effective_on" value="{{ old('effective_on') }}"></label>
            </div>
            <button class="jca-button-primary mt-4" type="submit"><x-ui.icon name="finance" size="size-4" /> Create draft schedule</button>
        </form>
    </div>
</section>
