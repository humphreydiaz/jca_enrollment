<x-layouts.app title="My teaching">
    <div class="space-y-8">
        <section class="jca-card p-6 sm:p-8" aria-labelledby="teaching-title">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-orange-500">Teacher workspace</p>
            <h1 id="teaching-title" class="mt-2 text-3xl font-black text-brand-navy-900">My active teaching assignments</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-muted">Only your currently active assignments are shown. Student lists, support summaries, attendance, and grades are not part of this phase.</p>
        </section>
        <section class="jca-card overflow-hidden" aria-labelledby="assignment-list-title"><div class="border-b border-border p-6"><h2 id="assignment-list-title" class="text-xl font-black text-brand-navy-900">Assigned sections</h2></div><div class="overflow-x-auto"><table class="min-w-full divide-y divide-border text-left text-sm"><thead class="bg-canvas text-muted"><tr><th class="px-6 py-3">School year</th><th class="px-6 py-3">Grade and section</th><th class="px-6 py-3">Assignment</th><th class="px-6 py-3">Schedule note</th></tr></thead><tbody class="divide-y divide-border bg-white">@forelse($assignments as $assignment)<tr><td class="px-6 py-4 font-semibold text-ink">{{ $assignment->section->academicYear->name }}</td><td class="px-6 py-4">{{ $assignment->section->gradeLevel->name }} · {{ $assignment->section->name }}</td><td class="px-6 py-4">{{ $assignment->assignment_type === 'adviser' ? 'Adviser' : $assignment->sectionSubject?->subject->name }}</td><td class="px-6 py-4 text-muted">{{ $assignment->sectionSubject?->schedule_text ?? 'No schedule note recorded.' }}</td></tr>@empty<tr><td colspan="4" class="px-6 py-8 text-center text-muted">You have no active teaching assignments.</td></tr>@endforelse</tbody></table></div></section>
    </div>
</x-layouts.app>
