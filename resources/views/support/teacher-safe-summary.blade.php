<x-layouts.app title="Student summary">
    <div class="mx-auto max-w-3xl space-y-4">
        <a class="inline-flex items-center gap-1 text-sm font-bold text-brand-navy-700 hover:underline" href="{{ route('academics.teacher.assignments', ['year' => $safeView->assignment->section->academicYear->public_id]) }}">← My teaching</a>
        <section class="jca-card p-6 sm:p-8">
            <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-brand-orange-500">Student summary</p>
            <h1 class="mt-2 text-2xl font-black text-brand-navy-900 sm:text-3xl">{{ $student->full_name }}</h1>
            <div class="mt-5">@include('support.partials.teacher-safe-summary')</div>
        </section>
    </div>
</x-layouts.app>
