<section id="exam-periods" data-section="exam-periods" class="scroll-mt-28 jca-card overflow-hidden" aria-labelledby="exam-periods-title">
    <div class="border-b border-border px-5 py-4 sm:px-6"><h2 id="exam-periods-title" class="text-xl font-black text-brand-navy-900">Examination periods</h2><p class="mt-1 text-sm text-muted">Configure eligibility cutoffs and controlled permit-issuance windows.</p></div>
    <div class="p-5 sm:p-6">
        <div class="grid gap-3 sm:grid-cols-2">@forelse ($periods as $period)<div class="rounded-xl border border-border p-4"><div class="flex justify-between gap-3"><div><p class="font-bold text-brand-navy-900">{{ $period->name }}</p><p class="mt-1 text-sm text-muted">{{ $period->starts_on->format('M j, Y') }} – {{ $period->ends_on->format('M j, Y') }}</p></div><x-ui.status-badge :status="$period->status" /></div></div>@empty<x-ui.empty-state class="sm:col-span-2" icon="academics" title="No examination periods" description="Add the first approved period below." />@endforelse</div>
        <form method="POST" action="{{ route('billing.configuration.exam-periods') }}" data-ajax class="mt-5 grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 xl:grid-cols-3">
            @csrf
            <label class="jca-label">School year<select class="jca-input" name="academic_year_id" required>@foreach ($academicYears as $year)<option value="{{ $year->id }}">{{ $year->name }}</option>@endforeach</select></label>
            <label class="jca-label">Code<input class="jca-input" name="code" required></label><label class="jca-label">Period name<input class="jca-input" name="name" required></label>
            <label class="jca-label">Starts<input class="jca-input" type="date" name="starts_on" required></label><label class="jca-label">Ends<input class="jca-input" type="date" name="ends_on" required></label><label class="jca-label">Eligibility cutoff<input class="jca-input" type="date" name="eligibility_cutoff_on" required></label>
            <label class="jca-label">Issue opens<input class="jca-input" type="date" name="issue_opens_on"></label><label class="jca-label">Issue closes<input class="jca-input" type="date" name="issue_closes_on"></label>
            <input type="hidden" name="status" value="planned"><button class="jca-button-primary sm:col-span-2 xl:col-span-3" type="submit">Add examination period</button>
        </form>
    </div>
</section>
