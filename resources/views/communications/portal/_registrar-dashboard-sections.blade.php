<section class="jca-card overflow-hidden" aria-labelledby="reg-academic-year-title">
    <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-6">
        <div class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700">
                <x-ui.icon name="academics" />
            </span>
            <div>
                <h2 id="reg-academic-year-title" class="font-black text-brand-navy-900">
                    {{ $activeYear?->name ?? 'No active academic year' }}
                </h2>
                @if ($activeYear)
                    <p class="text-sm text-muted">
                        {{ $activeYear->starts_on->format('M j, Y') }} &ndash; {{ $activeYear->ends_on->format('M j, Y') }}
                        @if ($activeYear->enrollment_opens_on && $activeYear->enrollment_closes_on)
                            <span class="mx-1.5 text-border">&bull;</span>
                            Enrollment window: {{ $activeYear->enrollment_opens_on->format('M j') }} &ndash; {{ $activeYear->enrollment_closes_on->format('M j') }}
                        @endif
                    </p>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-3">
            @if ($activeYear)
                <x-ui.status-badge :status="$activeYear->status" />
            @endif
            <a href="{{ route('academics.admin.index') }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-brand-navy-700 hover:underline">
                Manage <x-ui.icon name="arrow-right" size="size-4" />
            </a>
        </div>
    </div>
</section>

<section aria-labelledby="reg-pipeline-title">
    <div>
        <h2 id="reg-pipeline-title" class="text-xl font-black text-brand-navy-900">Enrollment pipeline</h2>
        <p class="mt-1 text-sm text-muted">Current distribution of all applications.</p>
    </div>

    @php
        $registrarStatuses = ['submitted', 'under_review', 'incomplete', 'assessment_pending', 'approved', 'enrolled'];
        $reviewable = ($enrollmentCounts['submitted'] ?? 0) + ($enrollmentCounts['under_review'] ?? 0) + ($enrollmentCounts['incomplete'] ?? 0) + ($enrollmentCounts['assessment_pending'] ?? 0);
    @endphp

    <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-6">
        @foreach ($registrarStatuses as $status)
            <div class="jca-card flex flex-col items-center gap-2 px-2 py-4 text-center">
                <span class="text-2xl font-black tabular-nums text-brand-navy-900">{{ $enrollmentCounts[$status] ?? 0 }}</span>
                <span class="max-w-full [&_.jca-status]:text-xs [&_.jca-status]:whitespace-normal [&_.jca-status]:text-center [&_.jca-status]:justify-center">
                    <x-ui.status-badge :status="$status" />
                </span>
            </div>
        @endforeach
    </div>
</section>

<div class="grid gap-8 lg:grid-cols-2">
    <section class="jca-card overflow-hidden" aria-labelledby="reg-grade-distribution-title">
        <div class="border-b border-border px-5 py-4 sm:px-6">
            <h2 id="reg-grade-distribution-title" class="text-lg font-black text-brand-navy-900">Enrollment by grade level</h2>
            <p class="mt-0.5 text-sm text-muted">Active enrollments for {{ $activeYear?->name ?? 'the current year' }}.</p>
        </div>
        @if ($enrollmentByGrade->isNotEmpty())
            @php $maxCount = $enrollmentByGrade->max('total'); @endphp
            <div class="divide-y divide-border">
                @foreach ($enrollmentByGrade as $grade)
                    <div class="flex items-center gap-4 px-5 py-3 sm:px-6">
                        <span class="w-24 shrink-0 text-sm font-bold text-brand-navy-900">{{ $grade->grade_name }}</span>
                        <div class="flex-1">
                            <div class="h-5 overflow-hidden rounded-full bg-brand-blue-100">
                                <div class="h-5 rounded-full bg-brand-navy-700 transition-all" style="width: {{ $maxCount > 0 ? round(($grade->total / $maxCount) * 100) : 0 }}%"></div>
                            </div>
                        </div>
                        <span class="w-10 text-right text-sm font-black tabular-nums text-brand-navy-900">{{ $grade->total }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-5 sm:p-6">
                <x-ui.empty-state icon="enrollment" title="No enrollment data" description="No active enrollments found for the current academic year." />
            </div>
        @endif
    </section>

    <section class="jca-card overflow-hidden" aria-labelledby="reg-section-capacity-title">
        <div class="flex items-center justify-between gap-4 border-b border-border px-5 py-4 sm:px-6">
            <div>
                <h2 id="reg-section-capacity-title" class="text-lg font-black text-brand-navy-900">Section capacity</h2>
                <p class="mt-0.5 text-sm text-muted">Confirmed students per section for {{ $activeYear?->name ?? 'the current year' }}.</p>
            </div>
            <a href="{{ route('section-assignment.index') }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-brand-navy-700 hover:underline">
                View all <x-ui.icon name="arrow-right" size="size-4" />
            </a>
        </div>
        @if ($sectionCapacity->isNotEmpty())
            <div class="divide-y divide-border">
                @foreach ($sectionCapacity as $section)
                    @php
                        $pct = $section->capacity > 0 ? round(($section->confirmed_count / $section->capacity) * 100) : 0;
                        $barColor = $pct >= 100 ? 'bg-brand-red-600' : ($pct >= 80 ? 'bg-brand-orange-500' : 'bg-brand-navy-700');
                    @endphp
                    <div class="flex items-center gap-4 px-5 py-3 sm:px-6">
                        <div class="w-24 shrink-0">
                            <span class="block text-sm font-bold text-brand-navy-900">{{ $section->name }}</span>
                            <span class="block text-xs text-muted">{{ $section->gradeLevel?->name }}</span>
                        </div>
                        <div class="flex-1">
                            <div class="h-5 overflow-hidden rounded-full bg-brand-blue-100">
                                <div class="h-5 rounded-full {{ $barColor }} transition-all" style="width: {{ min($pct, 100) }}%"></div>
                            </div>
                        </div>
                        <span class="w-16 text-right text-sm tabular-nums text-brand-navy-900">
                            <span class="font-black">{{ $section->confirmed_count }}</span><span class="text-muted">/{{ $section->capacity }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-5 sm:p-6">
                <x-ui.empty-state icon="sections" title="No sections" description="No active sections found for the current academic year." />
            </div>
        @endif
    </section>
</div>

<section aria-labelledby="reg-overview-title">
    <div>
        <h2 id="reg-overview-title" class="text-xl font-black text-brand-navy-900">At a glance</h2>
        <p class="mt-1 text-sm text-muted">Key numbers across your registrar scope.</p>
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <a href="{{ route('enrollment.registrar.index') }}" class="jca-card group flex flex-col p-5 transition hover:-translate-y-0.5 hover:border-brand-navy-700 hover:shadow-lg">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700 transition group-hover:bg-brand-navy-700 group-hover:text-white">
                    <x-ui.icon name="enrollment" />
                </span>
                <h3 class="font-extrabold text-brand-navy-900">Enrollment</h3>
            </div>
            <dl class="mt-4 flex-1 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-muted">Needs review</dt>
                    <dd class="font-bold tabular-nums {{ $reviewable > 0 ? 'text-brand-orange-500' : 'text-brand-navy-900' }}">{{ $reviewable }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted">Approved</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $enrollmentCounts['approved'] ?? 0 }}</dd>
                </div>
                <div class="flex justify-between border-t border-border pt-2">
                    <dt class="text-muted">Enrolled</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $enrollmentCounts['enrolled'] ?? 0 }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted">Rejected / Withdrawn</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ ($enrollmentCounts['rejected'] ?? 0) + ($enrollmentCounts['withdrawn'] ?? 0) }}</dd>
                </div>
            </dl>
        </a>

        <a href="{{ route('section-assignment.index') }}" class="jca-card group flex flex-col p-5 transition hover:-translate-y-0.5 hover:border-brand-navy-700 hover:shadow-lg">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700 transition group-hover:bg-brand-navy-700 group-hover:text-white">
                    <x-ui.icon name="sections" />
                </span>
                <h3 class="font-extrabold text-brand-navy-900">Sections & students</h3>
            </div>
            <dl class="mt-4 flex-1 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-muted">Active sections</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $sectionCapacity->count() }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted">Total placed</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $sectionCapacity->sum('confirmed_count') }}</dd>
                </div>
                <div class="flex justify-between border-t border-border pt-2">
                    <dt class="text-muted">Total student records</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $studentCount }}</dd>
                </div>
            </dl>
        </a>

        <a href="{{ route('support.queue') }}" class="jca-card group flex flex-col p-5 transition hover:-translate-y-0.5 hover:border-brand-navy-700 hover:shadow-lg">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700 transition group-hover:bg-brand-navy-700 group-hover:text-white">
                    <x-ui.icon name="support" />
                </span>
                <h3 class="font-extrabold text-brand-navy-900">Support review</h3>
            </div>
            <dl class="mt-4 flex-1 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-muted">Open cases (yours)</dt>
                    <dd class="font-bold tabular-nums {{ $openSupportCases > 0 ? 'text-brand-orange-500' : 'text-brand-navy-900' }}">{{ $openSupportCases }}</dd>
                </div>
            </dl>
        </a>
    </div>
</section>

<section class="jca-card overflow-hidden" aria-labelledby="reg-recent-applications-title">
    <div class="flex items-center justify-between gap-4 border-b border-border px-5 py-4 sm:px-6">
        <div>
            <h2 id="reg-recent-applications-title" class="text-lg font-black text-brand-navy-900">Recent applications</h2>
            <p class="mt-0.5 text-sm text-muted">Latest submissions awaiting review.</p>
        </div>
        <a class="inline-flex items-center gap-2 text-sm font-bold text-brand-navy-700 hover:underline" href="{{ route('enrollment.registrar.index') }}">
            View all <x-ui.icon name="arrow-right" size="size-4" />
        </a>
    </div>
    <div class="divide-y divide-border">
        @forelse ($recentEnrollments as $enrollment)
            <div class="flex items-center gap-4 px-5 py-4 sm:px-6">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700">
                    <x-ui.icon name="enrollment" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-brand-navy-900">
                        {{ $enrollment->student?->last_name }}, {{ $enrollment->student?->first_name }}
                    </span>
                    <span class="block text-xs text-muted">
                        {{ $enrollment->application_number }} &middot; {{ $enrollment->gradeLevel?->name }} &middot; {{ $enrollment->submitted_at?->diffForHumans() }}
                    </span>
                </span>
                <x-ui.status-badge :status="$enrollment->status" />
            </div>
        @empty
            <div class="p-5 sm:p-6">
                <x-ui.empty-state icon="enrollment" title="No pending applications" description="All applications have been reviewed. New submissions will appear here." />
            </div>
        @endforelse
    </div>
</section>
