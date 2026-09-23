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
                        <x-ui.status-badge :status="$enrollment->status" />
                    </div>

                    @if($assignment = $enrollment->currentSectionAssignment)
                        <div class="mt-3 rounded-lg bg-brand-blue-100 p-4">
                            <p class="font-bold text-brand-navy-900">Confirmed section: {{ $assignment->section->name }}</p>
                            <a class="mt-2 inline-block text-sm font-bold text-brand-navy-700 underline" href="{{ route('portal.section.print', $enrollment) }}">Print section and schedule</a>
                            @php($adviser = $assignment->section->teachingAssignments->first())
                            <p class="mt-1 text-sm">Adviser: {{ $adviser?->staffProfile?->user?->name ?? 'Not yet configured' }}</p>
                            <ul class="mt-3 space-y-1 text-sm">
                                @forelse($assignment->section->sectionSubjects as $sectionSubject)
                                    <li><strong>{{ $sectionSubject->subject->name }}:</strong> {{ $sectionSubject->schedule_text ?: 'Schedule not yet recorded' }}</li>
                                @empty
                                    <li>Schedule not yet configured.</li>
                                @endforelse
                            </ul>
                            <p class="mt-3 text-sm text-muted">Next action: continue school onboarding using JCA instructions.</p>
                        </div>
                    @elseif($enrollment->status === 'approved')
                        <p class="mt-3 rounded-lg bg-warning-100 p-3 text-sm text-warning-800">Section placement is awaiting staff confirmation.</p>
                    @endif

                    @if($enrollment->requirements->isNotEmpty())
                        <section class="mt-5 border-t border-border pt-5" aria-labelledby="guardian-requirements-{{ $enrollment->public_id }}">
                            <h3 id="guardian-requirements-{{ $enrollment->public_id }}" class="text-lg font-black text-brand-navy-900">Enrollment requirements</h3>
                            <p class="mt-1 text-sm text-muted">Upload a readable soft copy for review or submit the physical document to the Registrar. Hard copies remain required even when a soft copy is accepted.</p>
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
