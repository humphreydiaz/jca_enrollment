<x-layouts.app title="Restricted support review">
    <div class="space-y-6">
        <section class="jca-card p-6"><p class="text-sm font-bold uppercase tracking-[0.16em] text-brand-red-600">Restricted · audited access</p><h1 class="mt-2 text-3xl font-black text-brand-navy-900">Review: {{ $case->enrollment->student->full_name }}</h1><p class="mt-2 text-muted">Raw observations and rationale are visible only to Admin and the assigned Registrar. Use neutral school-review outcomes only.</p></section>
        @php
            $currentSummary = $case->summaries->whereNull('superseded_by')->sortByDesc('approved_at')->first();
            $learner = $case->enrollment->student;
        @endphp
        {{-- What has been recorded so far (append-only); the forms below add to it. --}}
        <section class="jca-card p-6" aria-labelledby="case-record-title">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="case-record-title" class="text-xl font-black text-brand-navy-900">Recorded so far</h2>
                    <p class="mt-1 text-sm text-muted">{{ $case->enrollment->gradeLevel?->name }} · {{ $case->enrollment->academicYear?->name }}@if ($learner->is_learner_with_disability && $learner->disability_type) · Application: {{ \App\Modules\People\Support\LearnerProfileOptions::DISABILITY_TYPES[$learner->disability_type] ?? $learner->disability_type }}@endif</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs font-bold">
                    <span class="rounded-md bg-slate-100 px-2 py-1 text-brand-navy-700">Case: {{ str($case->status)->headline() }}</span>
                    <span class="rounded-md px-2 py-1 {{ $case->final_outcome === 'approved' ? 'bg-success-100 text-success-700' : 'bg-warning-100 text-warning-800' }}">Outcome: {{ $case->final_outcome ? str($case->final_outcome)->headline() : 'Not decided' }}</span>
                </div>
            </div>
            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                <div>
                    <h3 class="text-sm font-extrabold uppercase tracking-[0.12em] text-muted">Review outcomes</h3>
                    <ol class="mt-2 space-y-2 text-sm">
                        @forelse ($case->assessments->sortByDesc('id') as $assessment)
                            <li class="rounded-lg bg-canvas p-3">
                                <p><strong>{{ str($assessment->outcome)->headline() }}</strong> <span class="text-muted">· {{ $assessment->completed_on?->format('d M Y') }}</span></p>
                                @if ($assessment->confidential_rationale)<p class="mt-1 whitespace-pre-line">{{ $assessment->confidential_rationale }}</p>@endif
                                @if ($assessment->next_action)<p class="mt-1 text-muted">Next action: {{ $assessment->next_action }}</p>@endif
                            </li>
                        @empty
                            <li class="text-muted">No outcome recorded yet.</li>
                        @endforelse
                    </ol>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold uppercase tracking-[0.12em] text-muted">Restricted notes</h3>
                    <ol class="mt-2 space-y-2 text-sm">
                        @forelse ($case->observations->sortByDesc('observed_at') as $observation)
                            <li class="rounded-lg bg-canvas p-3">
                                <p><strong>{{ $observation->category ?: 'School observation' }}</strong> <span class="text-muted">· {{ $observation->observed_at?->format('d M Y H:i') }}</span></p>
                                <p class="mt-1 whitespace-pre-line">{{ $observation->body }}</p>
                            </li>
                        @empty
                            <li class="text-muted">No notes yet.</li>
                        @endforelse
                    </ol>
                </div>
            </div>
            <div class="mt-5 rounded-xl border border-border p-4">
                <h3 class="text-sm font-extrabold uppercase tracking-[0.12em] text-muted">Teacher-safe summary teachers see now</h3>
                @if ($currentSummary)
                    <p class="mt-2 whitespace-pre-line text-sm">{{ $currentSummary->summary }}</p>
                    <p class="mt-1 text-xs text-muted">Approved {{ $currentSummary->approved_at?->format('d M Y') }}@if ($currentSummary->effective_from) · effective {{ $currentSummary->effective_from->format('d M Y') }}@endif @if ($currentSummary->effective_until) to {{ $currentSummary->effective_until->format('d M Y') }}@endif</p>
                @else
                    <p class="mt-2 text-sm text-muted">None yet. Teachers see nothing until a summary is approved below.</p>
                @endif
            </div>
        </section>
        <div class="grid gap-6 xl:grid-cols-2">
            <section class="jca-card p-6"><h2 class="text-xl font-black text-brand-navy-900">Generic review outcome</h2><form method="POST" action="{{ route('support.assessment',$case) }}" class="mt-5 grid gap-3">@csrf<label for="outcome" class="font-semibold">Outcome</label><select id="outcome" name="outcome" required class="rounded-lg border-border px-3 py-2"><option value="pending">Pending</option><option value="approved">Approved</option><option value="not_approved">Not approved</option><option value="more_information_required">More information required</option></select><label for="confidential_rationale" class="font-semibold">Restricted rationale</label><textarea id="confidential_rationale" name="confidential_rationale" rows="4" class="rounded-lg border-border px-3 py-2"></textarea><label for="next_action" class="font-semibold">Next action</label><input id="next_action" name="next_action" class="rounded-lg border-border px-3 py-2"><x-ui.button type="submit">Record append-only outcome</x-ui.button></form></section>
            <section class="jca-card p-6"><h2 class="text-xl font-black text-brand-navy-900">Restricted observation</h2><form method="POST" action="{{ route('support.observation',$case) }}" class="mt-5 grid gap-3">@csrf<label for="category" class="font-semibold">Neutral category</label><input id="category" name="category" placeholder="School observation" class="rounded-lg border-border px-3 py-2"><label for="body" class="font-semibold">Internal note</label><textarea id="body" name="body" required rows="5" class="rounded-lg border-border px-3 py-2"></textarea><x-ui.button type="submit" variant="secondary">Save restricted note</x-ui.button></form></section>
        </div>
        <section class="jca-card p-6"><h2 class="text-xl font-black text-brand-navy-900">Approved teacher-safe summary</h2><p class="mt-2 text-sm text-muted">Available only after an approved review outcome. Do not copy raw documents, diagnoses, or confidential rationale.</p><form method="POST" action="{{ route('support.summary',$case) }}" class="mt-5 grid gap-3 sm:grid-cols-2">@csrf<div class="sm:col-span-2"><label for="summary" class="font-semibold">Educational summary</label><textarea id="summary" name="summary" required rows="4" class="mt-1 block w-full rounded-lg border-border px-3 py-2"></textarea></div><label class="font-semibold">Effective from<input name="effective_from" type="date" class="mt-1 block w-full rounded-lg border-border px-3 py-2"></label><label class="font-semibold">Effective until<input name="effective_until" type="date" class="mt-1 block w-full rounded-lg border-border px-3 py-2"></label><div class="sm:col-span-2"><x-ui.button type="submit" variant="secondary">Approve summary version</x-ui.button></div></form></section>
        <section class="jca-card p-6"><h2 class="text-xl font-black text-brand-navy-900">Case event history</h2><ol class="mt-4 space-y-2 text-sm">@forelse($case->events->sortByDesc('occurred_at') as $event)<li class="rounded-lg bg-canvas p-3"><strong>{{ ucfirst(str_replace('_',' ',$event->event_type)) }}</strong><span class="text-muted"> · {{ $event->occurred_at->format('d M Y H:i') }}</span></li>@empty<li class="text-muted">No case events have been recorded.</li>@endforelse</ol></section>
    </div>
</x-layouts.app>
