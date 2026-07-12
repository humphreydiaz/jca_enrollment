<x-layouts.app title="Financial records">
    <section class="jca-card p-6">
        <h1 class="text-3xl font-black text-brand-navy-900">Financial records</h1>
        <p class="mt-2 text-slate-600">Academic enrollment and financial standing are shown separately.</p>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse($assessments as $assessment)
                <article class="rounded-lg border p-4"><h2 class="font-bold">{{ $assessment->enrollment->student->full_name }}</h2><p class="text-sm text-slate-600">{{ $assessment->enrollment->gradeLevel->name }} · {{ $assessment->enrollment->academicYear->name }}</p><p class="mt-2">{{ $assessment->assessment_reference }} · Revision {{ $assessment->revision_number }}</p><a class="jca-button-secondary mt-3 inline-flex" href="{{ route('billing.portal.show',$assessment) }}">Open financial record</a></article>
            @empty
                <p class="text-slate-600">No authorized finalized financial record is available.</p>
            @endforelse
        </div>
    </section>
</x-layouts.app>
