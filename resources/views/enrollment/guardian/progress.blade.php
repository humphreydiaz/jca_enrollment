<x-layouts.app title="My enrollment progress">
    <section class="jca-card p-6">
        <h1 class="text-3xl font-black text-brand-navy-900">My enrollment progress</h1>
        <p class="mt-2 text-sm text-muted">Only safe progress for children connected to this guardian account appears here.</p>

        <div class="mt-6 space-y-5">
            @forelse($enrollments as $enrollment)
                <article class="rounded-xl border border-border p-4 sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-bold">{{ $enrollment->student->full_name }} &middot; {{ $enrollment->academicYear->name }}</h2>
                            <p class="mt-1 text-sm text-muted">Reference {{ $enrollment->application_number }} &middot; {{ $enrollment->gradeLevel->name }}</p>
                        </div>
                        @php $learnerExitLabel = in_array($enrollment->status, ['approved', 'enrolled'], true) ? \App\Modules\People\Support\StudentExitOptions::exitLabel($enrollment->student?->status) : null; @endphp
                        @if ($learnerExitLabel)
                            <x-ui.status-badge :status="$enrollment->student->status" :label="$learnerExitLabel" />
                        @else
                            <x-ui.status-badge :status="$enrollment->status" />
                        @endif
                    </div>

                    <div class="mt-4">
                        <a class="{{ $enrollment->status === 'draft' ? 'jca-button-primary' : 'jca-button-secondary' }}" href="{{ route('enrollment.public.status', $enrollment) }}">
                            {{ $enrollment->status === 'draft' ? 'Complete application' : 'View application' }}
                        </a>
                    </div>

                    @if($assignment = $enrollment->currentSectionAssignment)
                        @php
                            $section = $assignment->section;
                            $adviser = $section->teachingAssignments->first();
                            $scheduleItems = $section->sectionSubjects->sortBy(function ($item) {
                                preg_match('/\b(\d{1,2}):(\d{2})\b/', $item->schedule_text ?? '', $time);
                                $start = isset($time[0]) ? sprintf('%02d:%s', (int) $time[1], $time[2]) : '99:99';

                                return $start.'|'.strtolower($item->subject->name);
                            });
                        @endphp
                        <section class="mt-4 overflow-hidden rounded-xl border border-border bg-canvas/70" aria-labelledby="guardian-section-{{ $enrollment->public_id }}">
                            <div class="flex flex-col gap-3 border-b border-border px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-5">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-brand-navy-700">Confirmed section</p>
                                    <h3 id="guardian-section-{{ $enrollment->public_id }}" class="mt-1 text-lg font-black text-brand-navy-900">{{ $section->name }}</h3>
                                    <p class="mt-1 text-sm text-muted">Capacity {{ $section->capacity }} &middot; Adviser: {{ $adviser?->staffProfile?->user?->name ?? 'Not yet configured' }}</p>
                                </div>
                                <a class="text-sm font-bold text-brand-navy-700 underline underline-offset-2 hover:text-brand-navy-900" href="{{ route('portal.section.print', $enrollment) }}">Print section and schedule</a>
                            </div>

                            @if($section->activity_schedule)
                                <x-academics.daily-activities :section="$section" class="border-b border-border px-4 py-3 sm:px-5" />
                            @endif

                            @if($scheduleItems->isNotEmpty() || ! $section->activity_schedule)
                            <div class="px-4 py-2 sm:px-5">
                                <h4 class="sr-only">Class schedule for {{ $section->name }}</h4>
                                <dl class="divide-y divide-border">
                                    @forelse($scheduleItems as $sectionSubject)
                                        <div class="grid gap-1 py-2 text-sm sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.9fr)] sm:items-baseline sm:gap-4">
                                            <dt class="font-medium text-brand-navy-900">{{ $sectionSubject->subject->name }}</dt>
                                            <dd class="text-muted">{{ $sectionSubject->schedule_text ?: 'Schedule not yet recorded' }}</dd>
                                            <dd class="text-ink sm:text-right"><span class="text-muted">Teacher: </span>{{ $sectionSubject->teacherNames() ?: 'To be assigned' }}</dd>
                                        </div>
                                    @empty
                                        <div class="py-3 text-sm text-muted">Schedule not yet configured.</div>
                                    @endforelse
                                </dl>
                            </div>
                            @endif
                            <p class="border-t border-border bg-white/70 px-4 py-3 text-sm text-muted sm:px-5">Next action: continue school onboarding using JCA instructions.</p>
                        </section>
                    @elseif($enrollment->status === 'approved')
                        <p class="mt-3 rounded-lg bg-warning-100 p-3 text-sm text-warning-800">Section placement is awaiting staff confirmation.</p>
                    @endif

                    @if($enrollment->requirements->isNotEmpty())
                        <section class="mt-5 border-t border-border pt-5" aria-labelledby="guardian-requirements-{{ $enrollment->public_id }}">
                            <h3 id="guardian-requirements-{{ $enrollment->public_id }}" class="text-lg font-black text-brand-navy-900">Enrollment requirements</h3>
                            <p class="mt-1 text-sm text-muted">Submit each physical document to the Registrar's office. The Registrar records when it is received and reviews it.</p>
                            <div class="mt-4 space-y-3">
                                @foreach($enrollment->requirements as $requirement)
                                    <x-enrollment.requirement-status :requirement="$requirement" />
                                @endforeach
                            </div>
                        </section>
                    @elseif($enrollment->status !== 'draft')
                        <x-ui.empty-state class="mt-5 border-t border-border pt-5" icon="enrollment" title="Requirement checklist unavailable" description="Please contact the JCA Registrar so the checklist can be configured for this application." />
                    @endif
                </article>
            @empty
                <x-ui.empty-state icon="enrollment" title="No linked enrollment progress" description="No enrollment records are currently available for children connected to this guardian account." />
            @endforelse
        </div>
    </section>
</x-layouts.app>
