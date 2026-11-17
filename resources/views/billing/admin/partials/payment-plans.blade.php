<section id="payment-plans" data-section="payment-plans" class="scroll-mt-28 jca-card overflow-hidden" aria-labelledby="payment-plans-title">
    <div class="border-b border-border px-5 py-4 sm:px-6"><h2 id="payment-plans-title" class="text-xl font-black text-brand-navy-900">Payment plans</h2><p class="mt-1 text-sm text-muted">Control when an assessed balance is due. Plans do not set tuition amounts or create a student's assessment.</p></div>
    <div class="p-5 sm:p-6">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950" role="note">
            <p class="font-bold">A payment plan is only one part of billing.</p>
            <p class="mt-1">Before a student can be billed, a fee schedule must also be published for the same school year and grade. Accounting then creates and finalizes the student's assessment before selecting a plan.</p>
        </div>

        <div class="mt-5 overflow-x-auto rounded-xl border border-border">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-muted">
                    <tr><th class="px-4 py-3">School year</th><th class="px-4 py-3">Grade</th><th class="px-4 py-3">Plan</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($planOptions as $plan)
                        @php $usage = $planOptionUsage[$plan->id] ?? 0; @endphp
                        <tr>
                            <td class="px-4 py-3 font-semibold text-brand-navy-900">{{ $plan->academicYear?->name }}</td>
                            <td class="px-4 py-3">{{ $plan->gradeLevel?->name ?? 'All grades' }}</td>
                            <td class="px-4 py-3"><p class="font-semibold">{{ $plan->name }}</p><p class="text-xs text-muted">{{ str($plan->code)->headline() }}</p></td>
                            <td class="px-4 py-3">
                                <x-ui.status-badge :status="$plan->is_active ? 'active' : 'inactive'" />
                                <p class="mt-1 text-xs text-muted">{{ $usage === 0 ? 'Not yet used' : 'In use by '.$usage.' assessment'.($usage === 1 ? '' : 's') }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-col items-end gap-2">
                                    <details class="text-left">
                                        <summary class="jca-button-secondary cursor-pointer list-none">Rename</summary>
                                        <form method="POST" action="{{ route('billing.configuration.plan-options.update', $plan) }}" data-ajax class="mt-2 w-72 rounded-xl border border-border bg-white p-3 text-left shadow-lg">
                                            @csrf @method('PUT')
                                            <label class="jca-label">Display name
                                                <input class="jca-input" name="name" value="{{ $plan->name }}" maxlength="255" required>
                                            </label>
                                            <p class="mt-2 text-xs text-muted">Only the label changes. Student schedules keep their own copy.</p>
                                            <button class="jca-button-primary mt-3 w-full" type="submit">Save name</button>
                                        </form>
                                    </details>

                                    <details class="text-left">
                                        <summary class="jca-button-secondary cursor-pointer list-none">{{ $plan->is_active ? 'Deactivate' : 'Activate' }}</summary>
                                        <form method="POST" action="{{ route('billing.configuration.plan-options.status', $plan) }}" data-ajax class="mt-2 w-72 rounded-xl border border-border bg-white p-3 text-left shadow-lg">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="is_active" value="{{ $plan->is_active ? 0 : 1 }}">
                                            <label class="jca-label">Reason
                                                <textarea class="jca-input" name="reason" rows="2" required placeholder="Why is this plan being {{ $plan->is_active ? 'deactivated' : 'activated' }}?"></textarea>
                                            </label>
                                            <p class="mt-2 text-xs text-muted">Historical assessments and payments will not be removed.</p>
                                            <button class="jca-button-primary mt-3 w-full" type="submit">Confirm {{ $plan->is_active ? 'deactivation' : 'activation' }}</button>
                                        </form>
                                    </details>

                                    @if ($usage === 0)
                                        <details class="text-left">
                                            <summary class="jca-button-danger cursor-pointer list-none">Delete</summary>
                                            <form method="POST" action="{{ route('billing.configuration.plan-options.destroy', $plan) }}" data-ajax class="mt-2 w-72 rounded-xl border border-border bg-white p-3 text-left shadow-lg">
                                                @csrf @method('DELETE')
                                                <p class="text-sm font-semibold text-brand-navy-900">Delete &ldquo;{{ $plan->name }}&rdquo;?</p>
                                                <p class="mt-1 text-xs text-muted">No student is using this plan, so it can be safely removed. This cannot be undone.</p>
                                                <button class="jca-button-danger mt-3 w-full" type="submit">Confirm delete</button>
                                            </form>
                                        </details>
                                    @else
                                        <span class="text-xs text-muted">Delete unavailable while in use</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-muted">No payment plans configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('billing.configuration.plan-options') }}" data-ajax class="mt-5 grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 xl:grid-cols-4">
            @csrf
            <div class="sm:col-span-2 xl:col-span-4"><h3 class="font-black text-brand-navy-900">Add a payment plan</h3><p class="mt-1 text-sm text-muted">Only one plan of each type is allowed per school year and grade.</p></div>
            <label class="jca-label">School year<select class="jca-input" name="academic_year_id" required>@foreach ($academicYears as $year)<option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->name }}</option>@endforeach</select></label>
            <label class="jca-label">Grade<select class="jca-input" name="grade_level_id"><option value="">All grades</option>@foreach ($gradeLevels as $grade)<option value="{{ $grade->id }}" @selected(old('grade_level_id') == $grade->id)>{{ $grade->name }}</option>@endforeach</select></label>
            <label class="jca-label">Plan type<select class="jca-input" name="code"><option value="full" @selected(old('code') === 'full')>Full payment</option><option value="semiannual" @selected(old('code') === 'semiannual')>Semiannual</option><option value="quarterly" @selected(old('code') === 'quarterly')>Quarterly</option><option value="monthly" @selected(old('code') === 'monthly')>Monthly</option></select></label>
            <label class="jca-label">Display name<input class="jca-input" name="name" value="{{ old('name') }}" placeholder="e.g. Grade 2 Monthly" required></label>
            <label class="jca-check"><input type="checkbox" name="is_active" value="1" checked> Active</label>
            <button class="jca-button-primary sm:col-span-2 xl:col-span-4" type="submit">Add payment plan</button>
        </form>
    </div>
</section>
