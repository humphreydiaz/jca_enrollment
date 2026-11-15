<x-layouts.app title="Review class grades">
    <div class="mx-auto max-w-4xl space-y-4">
        <a class="inline-flex items-center gap-1 text-sm font-bold text-brand-navy-700 hover:underline" href="{{ route('grading.admin.index', ['year' => $class['section']->academicYear->public_id]) }}">← Grade approval</a>
        <section class="jca-card p-5 sm:p-6">
            <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-brand-orange-500">Review class</p>
            <h1 class="mt-1 text-2xl font-black text-brand-navy-900">{{ $class['grade']->name }} · {{ $class['section']->name }} · {{ $class['subject']->name }}</h1>
            <div class="mt-5">@include('grading.partials.class-review')</div>
        </section>
    </div>
</x-layouts.app>
