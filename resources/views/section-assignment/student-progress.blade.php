<x-layouts.app title="My enrollment">
    <div class="space-y-6">
        <x-ui.page-header
            title="My enrollment and section"
            description="View your current and historical school-year enrollment records, confirmed section, adviser, and configured schedule."
            eyebrow="Student portal"
        />

        @if ($connectionState === 'unlinked')
            @if ($studentApplications->isEmpty())
                <x-ui.empty-state
                    class="jca-card"
                    icon="users"
                    title="Student account setup is incomplete"
                    description="This login is not connected to an approved student profile yet. Start one enrollment application, then return here to follow its status and requirement checklist."
                >
                    <x-slot:action>
                        <a class="jca-button-primary" href="{{ route('enrollment.public.start') }}">
                            <x-ui.icon name="enrollment" size="size-4" />
                            Start enrollment application
                        </a>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                @php($hasActiveApplication = $studentApplications->contains(fn ($application) => ! $application->isTerminal()))
                <div class="space-y-5">
                    @foreach ($studentApplications as $application)
                        <article class="jca-card overflow-hidden" aria-labelledby="application-{{ $application->public_id }}">
                            <header class="flex flex-col gap-4 border-b border-border p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6">
                                <div>
                                    <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Enrollment application</p>
                                    <h2 id="application-{{ $application->public_id }}" class="mt-1 text-xl font-black text-brand-navy-900">{{ $application->application_number }}</h2>
                                    <p class="mt-1 text-sm text-muted">{{ $application->gradeLevel->name }} &middot; {{ $application->academicYear->name }}</p>
                                </div>
                                <x-ui.status-badge :status="$application->status" />
                            </header>

                            <div class="grid gap-5 p-5 lg:grid-cols-2 sm:p-6">
                                <section class="rounded-xl border border-border p-5" aria-labelledby="timeline-{{ $application->public_id }}">
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-10 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700"><x-ui.icon name="reports" size="size-5" /></span>
                                        <div>
                                            <h3 id="timeline-{{ $application->public_id }}" class="font-black text-brand-navy-900">Application timeline</h3>
                                            <p class="text-sm text-muted">Latest status: {{ str($application->status)->replace('_', ' ')->title() }}</p>
                                        </div>
                                    </div>
                                    <ol class="mt-4 space-y-3 border-l-2 border-brand-blue-100 pl-4 text-sm">
                                        <li>
                                            <p class="font-bold text-brand-navy-900">Application created</p>
                                            <time class="text-muted" datetime="{{ $application->created_at->toIso8601String() }}">{{ $application->created_at->timezone('Asia/Manila')->format('d M Y, g:i A') }}</time>
                                        </li>
                                        @foreach ($application->history as $event)
                                            <li>
                                                <p class="font-bold text-brand-navy-900">{{ str($event->to_status)->replace('_', ' ')->title() }}</p>
                                                <time class="text-muted" datetime="{{ $event->changed_at->toIso8601String() }}">{{ $event->changed_at->timezone('Asia/Manila')->format('d M Y, g:i A') }}</time>
                                                @if ($event->reason)<p class="mt-1 text-muted">{{ $event->reason }}</p>@endif
                                            </li>
                                        @endforeach
                                    </ol>
                                </section>

                                <section class="rounded-xl border border-border p-5" aria-labelledby="requirements-{{ $application->public_id }}">
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-10 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700"><x-ui.icon name="enrollment" size="size-5" /></span>
                                        <div>
                                            <h3 id="requirements-{{ $application->public_id }}" class="font-black text-brand-navy-900">Enrollment requirements</h3>
                                            <p class="text-sm text-muted">Upload soft copies privately and submit hard copies when available.</p>
                                        </div>
                                    </div>
                                    @if ($application->requirements->isEmpty())
                                        <p class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-muted">
                                            {{ $application->status === 'draft' ? 'The checklist will appear after the draft is submitted.' : 'The requirement checklist is not available yet. Please contact the JCA Registrar.' }}
                                        </p>
                                    @else
                                        <div class="mt-4 space-y-3">
                                            @foreach ($application->requirements as $requirement)
                                                <x-enrollment.requirement-status :requirement="$requirement" />
                                            @endforeach
                                        </div>
                                    @endif
                                </section>
                            </div>

                            <footer class="flex flex-col gap-3 border-t border-border bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <p class="text-sm text-muted">
                                    @if ($application->status === 'draft')
                                        Complete and submit this draft before starting another application.
                                    @elseif ($application->isTerminal())
                                        This application is closed. You may start a new application when needed.
                                    @else
                                        JCA is processing this application. You cannot start another active application for the same school year.
                                    @endif
                                </p>
                                <a class="{{ $application->status === 'draft' ? 'jca-button-primary' : 'jca-button-secondary' }} shrink-0" href="{{ route('enrollment.public.status', $application) }}">
                                    <x-ui.icon name="{{ $application->status === 'draft' ? 'enrollment' : 'reports' }}" size="size-4" />
                                    {{ $application->status === 'draft' ? 'Resume draft' : 'View application status' }}
                                </a>
                            </footer>
                        </article>
                    @endforeach

                    @unless ($hasActiveApplication)
                        <div class="flex justify-end">
                            <a class="jca-button-primary" href="{{ route('enrollment.public.start') }}">
                                <x-ui.icon name="enrollment" size="size-4" />
                                Start a new application
                            </a>
                        </div>
                    @endunless
                </div>
            @endif
        @elseif ($connectionState === 'identity_mismatch')
            <section class="jca-card border-red-200 p-6 sm:p-8" role="alert" aria-labelledby="identity-mismatch-title">
                <div class="flex gap-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-red-50 text-brand-red-600"><x-ui.icon name="warning" /></span>
                    <div>
                        <h2 id="identity-mismatch-title" class="text-xl font-black text-brand-red-600">Student account connection needs correction</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-muted">The login name does not match the connected student profile, so no enrollment, section, schedule, or financial information is being shown. Ask an Admin to disconnect the incorrect account in People and connect the login to the matching approved student profile.</p>
                    </div>
                </div>
            </section>
        @else
        <div class="space-y-5">
            @forelse ($enrollments as $enrollment)
                <article class="jca-card overflow-hidden">
                    <div class="flex flex-col gap-4 border-b border-border p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6">
                        <div>
                            <h2 class="text-xl font-black text-brand-navy-900">{{ $enrollment->academicYear->name }} · {{ $enrollment->gradeLevel->name }}</h2>
                            <p class="mt-1 text-sm text-muted">Application {{ $enrollment->application_number }}</p>
                        </div>
                        <x-ui.status-badge :status="$enrollment->status" />
                    </div>

                    <div class="p-5 sm:p-6">
                        @if ($assignment = $enrollment->currentSectionAssignment)
                            @php($adviser = $assignment->section->teachingAssignments->first())
                            <div class="grid gap-5 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                                <section class="rounded-xl bg-brand-blue-100 p-5" aria-labelledby="section-{{ $enrollment->public_id }}">
                                    <span class="grid size-11 place-items-center rounded-xl bg-white text-brand-navy-700 shadow-sm"><x-ui.icon name="sections" /></span>
                                    <p class="mt-4 text-sm font-bold text-brand-navy-700">Current placement</p>
                                    <h3 id="section-{{ $enrollment->public_id }}" class="mt-1 text-2xl font-black text-brand-navy-900">Confirmed section: {{ $assignment->section->name }}</h3>
                                    <p class="mt-3 text-sm text-brand-navy-900"><strong>Adviser:</strong> {{ $adviser?->staffProfile?->user?->name ?? 'Not yet configured' }}</p>
                                    <a class="jca-button-secondary mt-5" href="{{ route('portal.section.print', $enrollment) }}"><x-ui.icon name="reports" /> Print section and schedule</a>
                                </section>

                                <section class="rounded-xl border border-border p-5" aria-labelledby="schedule-{{ $enrollment->public_id }}">
                                    <div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-slate-100 text-brand-navy-700"><x-ui.icon name="academics" /></span><h3 id="schedule-{{ $enrollment->public_id }}" class="text-lg font-black text-brand-navy-900">Class schedule</h3></div>
                                    <ul class="mt-4 divide-y divide-border">
                                        @forelse ($assignment->section->sectionSubjects as $sectionSubject)
                                            <li class="grid gap-1 py-3 sm:grid-cols-[minmax(10rem,0.7fr)_minmax(0,1.3fr)] sm:gap-4"><strong class="text-sm text-brand-navy-900">{{ $sectionSubject->subject->name }}</strong><span class="text-sm text-muted">{{ $sectionSubject->schedule_text ?: 'Schedule not yet recorded' }}</span></li>
                                        @empty
                                            <li><x-ui.empty-state icon="academics" title="No schedule configured" description="Your subjects and meeting schedule will appear here after school staff complete the section schedule." /></li>
                                        @endforelse
                                    </ul>
                                </section>
                            </div>
                        @else
                            <x-ui.empty-state icon="sections" title="Section placement is pending" description="JCA staff will confirm your section after the academic enrollment requirements are completed." />
                        @endif

                        @if($enrollment->requirements->isNotEmpty())
                            <section class="mt-6 border-t border-border pt-5" aria-labelledby="connected-requirements-{{ $enrollment->public_id }}">
                                <h3 id="connected-requirements-{{ $enrollment->public_id }}" class="text-lg font-black text-brand-navy-900">Requirement record</h3>
                                <p class="mt-1 text-sm text-muted">Soft-copy evidence and the continuing hard-copy obligation are shown separately.</p>
                                <div class="mt-4 space-y-3">
                                    @foreach($enrollment->requirements as $requirement)
                                        <x-enrollment.requirement-status :requirement="$requirement" :show-upload="false" />
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>
                </article>
            @empty
                <x-ui.empty-state class="jca-card" icon="enrollment" title="No enrollment records yet" description="Your account is connected to a student profile, but no enrollment record has been added to it yet. Contact the Registrar if you believe a record is missing." />
            @endforelse
        </div>
        @endif
    </div>
</x-layouts.app>
