<x-layouts.app title="Final grades">
    @php
        // One table per learner and school year (newest first); each subject names the teacher who handled it.
        $groups = $grades->groupBy('enrollment_id')
            ->sortByDesc(fn ($items) => $items->first()->enrollment->academicYear->starts_on);
    @endphp
    <div class="space-y-6">
        <header class="jca-card p-6 sm:p-8"><p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-orange-500">School record</p><h1 class="mt-2 text-3xl font-black text-brand-navy-900">Final grades</h1><p class="mt-3 text-sm text-muted">Only complete final grades approved by the school Registrar appear here. A result being corrected is hidden until it is approved again.</p></header>
        @forelse($groups as $items)
            @php($enrollment = $items->first()->enrollment)
            <section class="jca-card overflow-x-auto" aria-labelledby="grades-{{ $enrollment->public_id }}">
                <h2 id="grades-{{ $enrollment->public_id }}" class="px-4 pt-4 text-lg font-black text-brand-navy-900 sm:px-5">{{ $enrollment->student->full_name }}</h2>
                <p class="px-4 pb-3 text-sm text-muted sm:px-5">{{ $enrollment->academicYear->name }} · {{ $enrollment->gradeLevel->name }}</p>
                <table class="w-full min-w-[700px] text-left text-sm"><thead class="bg-brand-blue-50 text-brand-navy-900"><tr><th scope="col" class="p-3">Subject</th><th scope="col" class="p-3">Teacher</th>@for($quarter=1;$quarter<=4;$quarter++)<th scope="col" class="p-3">Q{{ $quarter }}</th>@endfor<th scope="col" class="p-3">Final</th></tr></thead><tbody class="divide-y divide-border">
                @foreach($items->sortBy('subject_name_snapshot') as $grade)<tr><td class="p-3">{{ $grade->subject_name_snapshot }}</td><td class="p-3">{{ $grade->teachingAssignment?->staffProfile?->user?->name ?? '—' }}</td>@for($quarter=1;$quarter<=4;$quarter++)<td class="p-3">{{ $grade->{'quarter_'.$quarter} }}</td>@endfor<td class="p-3 font-black text-brand-navy-900">{{ $grade->final_grade }}</td></tr>
                @endforeach
                </tbody></table>
            </section>
        @empty
            <section class="jca-card p-6 text-center text-sm text-muted">No finalized grades are available for your authorized student records yet.</section>
        @endforelse
    </div>
</x-layouts.app>
