<x-layouts.app title="My teaching">
    <div class="space-y-8">
        <section class="jca-card p-6 sm:p-8" aria-labelledby="teaching-title"><p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-orange-500">Teacher workspace</p><h1 id="teaching-title" class="mt-2 text-3xl font-black text-brand-navy-900">My active teaching assignments</h1><p class="mt-3 max-w-3xl text-sm leading-6 text-muted">Only your active assignments and students currently confirmed in those sections are shown. Attendance and grades are outside this system's current scope.</p></section>
        <section class="jca-card overflow-hidden" aria-labelledby="assignment-list-title">
            <div class="border-b border-border p-6"><h2 id="assignment-list-title" class="text-xl font-black text-brand-navy-900">Assigned sections</h2></div>
            <div class="divide-y divide-border">
                @forelse($assignments as $assignment)
                    <article class="p-6">
                        <h3 class="font-black text-brand-navy-900">{{ $assignment->section->academicYear->name }} · {{ $assignment->section->gradeLevel->name }} · {{ $assignment->section->name }}</h3>
                        <p class="mt-1 text-sm font-semibold text-brand-orange-500">{{ $assignment->assignment_type === 'adviser' ? 'Adviser' : ($assignment->sectionSubject?->subject->name ?? 'Subject teacher') }}</p>

                        <h4 class="mt-4 font-bold text-brand-navy-900">Class schedule</h4>
                        <ul class="mt-2 divide-y divide-border overflow-hidden rounded-lg border border-border">
                            @forelse($assignment->section->sectionSubjects as $sectionSubject)
                                <li class="grid gap-1 px-4 py-3 sm:grid-cols-[minmax(10rem,0.7fr)_minmax(0,1.3fr)] sm:gap-4 {{ $assignment->sectionSubject && $assignment->sectionSubject->id === $sectionSubject->id ? 'bg-brand-blue-50' : '' }}">
                                    <strong class="text-sm text-brand-navy-900">{{ $sectionSubject->subject->name }}@if($assignment->sectionSubject && $assignment->sectionSubject->id === $sectionSubject->id)<span class="ml-2 rounded-full bg-brand-blue-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-brand-navy-700">Your class</span>@endif</strong>
                                    <span class="text-sm text-muted">{{ $sectionSubject->schedule_text ?: 'Schedule not yet recorded' }}</span>
                                </li>
                            @empty
                                <li class="px-4 py-3 text-sm text-muted">No subjects have been scheduled for this section yet.</li>
                            @endforelse
                        </ul>

                        <h4 class="mt-6 font-bold text-brand-navy-900">Current students</h4>
                        <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                            @forelse($studentsBySection->get($assignment->section_id,collect())->unique('enrollment.student_id') as $sectionAssignment)
                                <li><a class="block rounded-lg bg-canvas p-3 font-semibold text-brand-navy-700 underline" href="{{ route('teacher.students.support-summary',$sectionAssignment->enrollment->student) }}">{{ $sectionAssignment->enrollment->student->full_name }} · instructional support</a></li>
                            @empty
                                <li class="text-sm text-muted">No students are currently confirmed in this section.</li>
                            @endforelse
                        </ul>
                    </article>
                @empty
                    <div class="p-8 text-center text-muted">You have no active teaching assignments.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
