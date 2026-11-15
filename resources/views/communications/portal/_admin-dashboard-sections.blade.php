@if ($urgentMissingAssessments > 0)
<section class="jca-card cursor-pointer border-2 border-red-300 bg-red-50 p-5 sm:p-6 transition hover:bg-red-100" onclick="window.location.href='{{ route('billing.index') }}#awaiting-assessment'">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-red-800">
                URGENT — Enrolled Students Without Fee Assessment
            </h2>
            <p class="mt-2 text-sm text-red-700">
                {{ $urgentMissingAssessments }}
                enrolled student(s) have no fee assessment.
                Please review their billing records.
            </p>
        </div>
        <a href="{{ route('billing.index') }}"
           class="inline-flex items-center rounded-lg bg-red-700 px-4 py-2 text-sm font-bold text-white hover:bg-red-800">
            View in Billing
        </a>
    </div>
</section>
<section class="jca-card overflow-hidden" aria-labelledby="academic-year-title">
    <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-6">
        <div class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700">
                <x-ui.icon name="academics" />
            </span>
            <div>
                <h2 id="academic-year-title" class="font-black text-brand-navy-900">
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

<section aria-labelledby="pipeline-title">
    <div>
        <h2 id="pipeline-title" class="text-xl font-black text-brand-navy-900">Enrollment pipeline</h2>
        <p class="mt-1 text-sm text-muted">Current distribution across all academic years.</p>
    </div>

    <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-5 xl:grid-cols-9">
        @foreach (['submitted', 'under_review', 'incomplete', 'assessment_pending', 'approved', 'enrolled', 'rejected', 'withdrawn', 'cancelled'] as $status)
            <div class="jca-card flex flex-col items-center gap-2 px-2 py-4 text-center">
                <span class="text-2xl font-black tabular-nums text-brand-navy-900">{{ $enrollmentCounts[$status] ?? 0 }}</span>
                <span class="max-w-full [&_.jca-status]:text-xs [&_.jca-status]:whitespace-normal [&_.jca-status]:text-center [&_.jca-status]:justify-center">
                    <x-ui.status-badge :status="$status" />
                </span>
            </div>
        @endforeach
    </div>
</section>

<section class="jca-card overflow-hidden" aria-labelledby="grade-distribution-title">
    <div class="border-b border-border px-5 py-4 sm:px-6">
        <h2 id="grade-distribution-title" class="text-lg font-black text-brand-navy-900">Enrollment by grade level</h2>
        <p class="mt-0.5 text-sm text-muted">Active enrollments for {{ $activeYear?->name ?? 'the current year' }}.</p>
    </div>
    @if ($enrollmentByGrade->isNotEmpty())
        @php $maxCount = $enrollmentByGrade->max('total'); @endphp
        <div class="divide-y divide-border">
            @foreach ($enrollmentByGrade as $grade)
                <div class="flex items-center gap-4 px-5 py-3 sm:px-6">
                    <span class="w-28 shrink-0 text-sm font-bold text-brand-navy-900">{{ $grade->grade_name }}</span>
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

<section aria-labelledby="operations-title">
    <div>
        <h2 id="operations-title" class="text-xl font-black text-brand-navy-900">Operations overview</h2>
        <p class="mt-1 text-sm text-muted">System-wide counts for the current period.</p>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <a href="{{ route('billing.index') }}" class="jca-card group flex flex-col p-5 transition hover:-translate-y-0.5 hover:border-brand-navy-700 hover:shadow-lg">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700 transition group-hover:bg-brand-navy-700 group-hover:text-white">
                    <x-ui.icon name="billing" />
                </span>
                <h3 class="font-extrabold text-brand-navy-900">Financial</h3>
            </div>
            <dl class="mt-4 flex-1 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-muted">Draft payments</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $financialSummary['draft_payments'] }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted">Draft assessments</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $financialSummary['draft_assessments'] }}</dd>
                </div>
                <div class="flex justify-between border-t border-border pt-2">
                    <dt class="text-muted">Posted today</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ number_format($financialSummary['posted_total_today'], 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted">Posted this month</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ number_format($financialSummary['posted_total_month'], 2) }}</dd>
                </div>
            </dl>
        </a>

        <a href="{{ route('identity.admin.users.index') }}" class="jca-card group flex flex-col p-5 transition hover:-translate-y-0.5 hover:border-brand-navy-700 hover:shadow-lg">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700 transition group-hover:bg-brand-navy-700 group-hover:text-white">
                    <x-ui.icon name="users" />
                </span>
                <h3 class="font-extrabold text-brand-navy-900">User accounts</h3>
            </div>
            <dl class="mt-4 flex-1 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-muted">Total accounts</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $userSummary['total'] }}</dd>
                </div>
                <div class="flex justify-between border-t border-border pt-2">
                    <dt class="text-muted">Active</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $userSummary['active'] }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted">Invited</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $userSummary['invited'] }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted">Disabled</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $userSummary['disabled'] }}</dd>
                </div>
            </dl>
        </a>

        <a href="{{ route('communications.admin') }}" class="jca-card group flex flex-col p-5 transition hover:-translate-y-0.5 hover:border-brand-navy-700 hover:shadow-lg">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700 transition group-hover:bg-brand-navy-700 group-hover:text-white">
                    <x-ui.icon name="communications" />
                </span>
                <h3 class="font-extrabold text-brand-navy-900">Communications</h3>
            </div>
            <dl class="mt-4 flex-1 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-muted">Failed SMS</dt>
                    <dd class="font-bold tabular-nums {{ $commsSummary['failed_sms'] > 0 ? 'text-brand-red-600' : 'text-brand-navy-900' }}">{{ $commsSummary['failed_sms'] }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted">Pending SMS</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $commsSummary['pending_sms'] }}</dd>
                </div>
                <div class="flex justify-between border-t border-border pt-2">
                    <dt class="text-muted">Published announcements</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $commsSummary['published_announcements'] }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted">Draft announcements</dt>
                    <dd class="font-bold tabular-nums text-brand-navy-900">{{ $commsSummary['draft_announcements'] }}</dd>
                </div>
            </dl>
        </a>
    </div>
</section>

<section class="jca-card overflow-hidden" aria-labelledby="recent-activity-title">
    <div class="flex items-center justify-between gap-4 border-b border-border px-5 py-4 sm:px-6">
        <div>
            <h2 id="recent-activity-title" class="text-lg font-black text-brand-navy-900">Recent activity</h2>
            <p class="mt-0.5 text-sm text-muted">Last 5 audit events across the system.</p>
        </div>
        <a class="inline-flex items-center gap-2 text-sm font-bold text-brand-navy-700 hover:underline" href="{{ route('reporting.audit.index') }}">
            View all <x-ui.icon name="arrow-right" size="size-4" />
        </a>
    </div>
    <div class="divide-y divide-border">
        @forelse ($recentAudit as $log)
            <div class="flex items-center gap-4 px-5 py-4 sm:px-6">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700">
                    <x-ui.icon name="audit" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-brand-navy-900">{{ collect(explode('.', $log->action))->map(fn ($part) => \Illuminate\Support\Str::headline($part))->join(' > ') }}</span>
                    <span class="block text-xs text-muted">{{ $log->actor?->name ?? 'System' }} &middot; {{ $log->occurred_at->diffForHumans() }}</span>
                </span>
                <x-ui.status-badge :status="$log->outcome" />
            </div>
        @empty
            <div class="p-5 sm:p-6">
                <x-ui.empty-state icon="audit" title="No recent activity" description="Audit events will appear here as system actions occur." />
            </div>
        @endforelse
    </div>
</section>
@endif
