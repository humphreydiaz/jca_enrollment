{{-- Teacher-safe student summary. Shared by the My teaching dialog and the full-page fallback. --}}
@php($placement = $safeView->assignment)
<dl class="grid grid-cols-2 gap-x-4 gap-y-3 rounded-xl bg-canvas p-4 text-sm sm:grid-cols-3">
    <div><dt class="text-xs font-extrabold uppercase tracking-wider text-muted">Student ID</dt><dd class="mt-0.5 font-bold tabular-nums text-brand-navy-900">{{ $student->student_number ?? '—' }}</dd></div>
    <div><dt class="text-xs font-extrabold uppercase tracking-wider text-muted">Grade &amp; section</dt><dd class="mt-0.5 font-bold text-brand-navy-900">{{ $placement->section->gradeLevel->name }} · {{ $placement->section->name }}</dd></div>
    <div class="col-span-2 sm:col-span-1"><dt class="text-xs font-extrabold uppercase tracking-wider text-muted">School year</dt><dd class="mt-0.5 font-bold text-brand-navy-900">{{ $placement->section->academicYear->code }}</dd></div>
</dl>

<section class="mt-5" aria-labelledby="support-notes-heading">
    <h3 id="support-notes-heading" class="text-sm font-extrabold uppercase tracking-wider text-brand-navy-700">Instructional support</h3>
    @if ($safeView->summary)
        <div class="mt-2 rounded-xl border border-brand-blue-100 bg-brand-blue-100/50 p-4">
            <p class="whitespace-pre-line leading-7 text-ink">{{ $safeView->summary->summary }}</p>
            <p class="mt-3 text-xs font-semibold text-muted">
                Approved for classroom use · effective {{ $safeView->summary->effective_from?->format('M j, Y') ?? 'now' }}{{ $safeView->summary->effective_until ? ' to '.$safeView->summary->effective_until->format('M j, Y') : '' }}
            </p>
        </div>
    @else
        <p class="mt-2 rounded-xl border border-dashed border-border p-4 text-sm text-muted">No instructional support notes are on file for this student. Teach as usual and speak with the Registrar if you notice something that needs follow-up.</p>
    @endif
</section>

<p class="mt-5 text-xs leading-5 text-muted">Only the approved classroom summary is shown here. Assessments, observations, documents, and reviewer notes stay with the support team.</p>
