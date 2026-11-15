
@php
    $dashboardUser = auth()->user();
    $isStudentDashboard = $dashboardUser->roles->contains(fn ($role) => strtolower($role->name) === 'student');
    $isGuardianDashboard = $dashboardUser->hasRole('guardian');
    $dashboardStudentNumber = $isStudentDashboard
        ? \Illuminate\Support\Facades\DB::table('student_profiles')
            ->where('user_id', $dashboardUser->id)
            ->value('student_number')
        : null;
@endphp
<x-layouts.app title="Dashboard">
    <div class="space-y-8">
        <section class="jca-card overflow-hidden" aria-labelledby="dashboard-title">
            <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <p class="text-sm font-bold text-brand-orange-500">Welcome back</p>
                    <h1 id="dashboard-title" class="mt-1 text-3xl font-black tracking-tight text-brand-navy-900 sm:text-4xl">{{ auth()->user()->name }}</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-muted sm:text-base">Here is the work currently waiting within your authorized JCA responsibilities.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($isStudentDashboard)
                            <x-ui.status-badge tone="info">{{ $dashboardStudentNumber ?: 'Student number unavailable' }}</x-ui.status-badge>
                        @else
                            @foreach (auth()->user()->roles as $role)
                                <x-ui.status-badge tone="info">{{ $role->name }}</x-ui.status-badge>
                            @endforeach
                        @endif
                    </div>
                </div>
                <div class="hidden size-28 place-items-center rounded-full bg-brand-blue-100 text-brand-navy-700 lg:grid" aria-hidden="true">
                    <x-ui.icon name="dashboard" size="size-12" />
                </div>
            </div>
            <div class="h-1.5 bg-gradient-to-r from-brand-navy-700 via-brand-orange-500 to-brand-gold-500"></div>
        </section>

        @if ($draftApplication ?? null)
            <section class="jca-card flex flex-col gap-4 border-brand-orange-500/50 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8" aria-labelledby="draft-application-title">
                <div class="flex gap-4">
                    <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-brand-orange-500/10 text-brand-orange-500" aria-hidden="true">
                        <x-ui.icon name="enrollment" />
                    </span>
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Next step</p>
                        <h2 id="draft-application-title" class="mt-1 text-xl font-black text-brand-navy-900">{{ $isGuardianDashboard ? 'Draft Enrollment' : 'Finish the enrollment application' }}</h2>
                        <p class="mt-1 text-sm text-muted">
                            {{ $draftApplication->student?->full_name }}
                            &middot; {{ $draftApplication->gradeLevel?->name }}
                            &middot; {{ $draftApplication->academicYear?->name }}.
                            The form is not submitted yet, so the Registrar cannot review it.
                        </p>
                    </div>
                </div>
                <a class="jca-button-primary shrink-0" href="{{ route('enrollment.public.status', $draftApplication) }}">Complete application</a>
            </section>
        @endif

        @php
            $teacherCards = collect($cards)->filter(fn ($card) => $card['label'] === 'My students')->values();
            $attentionCards = collect($cards)
                ->reject(fn ($card) => $card['label'] === 'My students')
                ->reject(fn ($card) => $isGuardianDashboard && $card['label'] === 'Enrollment action required')
                ->values();
        @endphp

        @if ($teacherCards->isNotEmpty())
            <section aria-labelledby="my-teaching-title">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 id="my-teaching-title" class="text-xl font-black text-brand-navy-900">My Teaching</h2>
                        <p class="mt-1 text-sm text-muted">Your assigned classes and students.</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($teacherCards as $card)
                        @php
                            $cardIcon = match ($card['label']) {
                                'Enrollment review', 'Applications' => 'enrollment',
                                'SMS follow-up' => 'communications',
                                'Billing work' => 'billing',
                                'User accounts' => 'users',
                                'Awaiting placement' => 'sections',
                                'Support cases' => 'support',
                                'Enrolled students' => 'people',
                                'Needs assessment' => 'finance',
                                'Draft payments' => 'billing',
                                'Permits issued today' => 'check-all',
                                'My students' => 'people',
                                'Enrollment action required' => 'enrollment',
                                'My enrollment' => 'progress',
                                default => 'dashboard',
                            };
                        @endphp

                        <a href="{{ $card['route'] }}"
                           class="jca-card group flex min-h-44 flex-col p-5 transition hover:-translate-y-0.5 hover:border-brand-navy-700 hover:shadow-lg">
                            <div class="flex items-start justify-between gap-4">
                                <span class="grid size-11 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700 transition group-hover:bg-brand-navy-700 group-hover:text-white">
                                    <x-ui.icon :name="$cardIcon" />
                                </span>
                                <span class="text-3xl font-black tabular-nums text-brand-navy-900">{{ $card['value'] }}</span>
                            </div>
                            <div class="mt-auto pt-5">
                                <h3 class="font-extrabold text-brand-navy-900">{{ $card['label'] }}</h3>
                                <div class="mt-1 flex items-center justify-between gap-3 text-sm text-muted">
                                    <span>{{ $card['detail'] }}</span>
                                    <x-ui.icon name="arrow-right" size="size-4" class="shrink-0 text-brand-navy-700 transition group-hover:translate-x-1" />
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($attentionCards->isNotEmpty())
            <section aria-labelledby="next-actions-title">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 id="next-actions-title" class="text-xl font-black text-brand-navy-900">{{ $isStudentDashboard ? 'My Enrollment' : 'Attention needed' }}</h2>
                        <p class="mt-1 text-sm text-muted">{{ $isStudentDashboard ? 'View your current and previous enrollment records.' : 'Records requiring action within your authorized responsibilities.' }}</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($attentionCards as $card)
                        @php
                            $cardIcon = match ($card['label']) {
                                'Enrollment review', 'Applications' => 'enrollment',
                                'SMS follow-up' => 'communications',
                                'Billing work' => 'billing',
                                'User accounts' => 'users',
                                'Awaiting placement' => 'sections',
                                'Support cases' => 'support',
                                'Enrolled students' => 'people',
                                'Needs assessment' => 'finance',
                                'Draft payments' => 'billing',
                                'Permits issued today' => 'check-all',
                                'My students' => 'people',
                                'Enrollment action required' => 'enrollment',
                                'My enrollment' => 'progress',
                                default => 'dashboard',
                            };
                        @endphp

                        <a href="{{ $card['route'] }}"
                           class="jca-card group flex min-h-44 flex-col p-5 transition hover:-translate-y-0.5 hover:border-brand-navy-700 hover:shadow-lg">
                            <div class="flex items-start justify-between gap-4">
                                <span class="grid size-11 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700 transition group-hover:bg-brand-navy-700 group-hover:text-white">
                                    <x-ui.icon :name="$cardIcon" />
                                </span>
                                <span class="text-3xl font-black tabular-nums text-brand-navy-900">{{ $card['value'] }}</span>
                            </div>
                            <div class="mt-auto pt-5">
                                <h3 class="font-extrabold text-brand-navy-900">{{ $card['label'] }}</h3>
                                <div class="mt-1 flex items-center justify-between gap-3 text-sm text-muted">
                                    <span>{{ $card['detail'] }}</span>
                                    <x-ui.icon name="arrow-right" size="size-4" class="shrink-0 text-brand-navy-700 transition group-hover:translate-x-1" />
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @isset($guardianChildren)
            <section aria-labelledby="my-children-title">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 id="my-children-title" class="text-xl font-black text-brand-navy-900">My Children</h2>
                        <p class="mt-1 text-sm text-muted">Student information and enrollment records linked to your account.</p>
                    </div>
                    <span class="rounded-full bg-brand-blue-100 px-4 py-2 text-sm font-extrabold text-brand-navy-700">
                        {{ $guardianChildren->count() }} {{ $guardianChildren->count() === 1 ? 'Child' : 'Children' }}
                    </span>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($guardianChildren as $child)
                        @php
                            $student = $child['student'];
                            $enrollment = $child['enrollment'];
                            $status = $enrollment?->status ?? 'not_enrolled';
                            // A learner who left the school is shown as Transferred Out / Dropped, never Enrolled.
                            if (\App\Modules\People\Support\StudentExitOptions::isExited($student->status ?? null)) {
                                $status = $student->status;
                            }
                            $statusLabel = match ($status) {
                                'under_review' => 'Under Review',
                                'assessment_pending' => 'Assessment Pending',
                                'not_enrolled' => 'Not Enrolled',
                                default => ucwords(str_replace('_', ' ', $status)),
                            };
                            $statusClass = match ($status) {
                                'enrolled' => 'bg-green-100 text-green-800',
                                'approved' => 'bg-blue-100 text-blue-800',
                                'submitted', 'under_review', 'assessment_pending' => 'bg-amber-100 text-amber-800',
                                'draft', 'incomplete' => 'bg-orange-100 text-orange-800',
                                'rejected', 'cancelled', 'withdrawn', 'dropped' => 'bg-red-100 text-red-800',
                                'transferred_out' => 'bg-orange-100 text-orange-800',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp

                        <a href="{{ route('guardian.enrollment.progress') }}"
                           class="jca-card group flex flex-col p-5 transition hover:-translate-y-0.5 hover:border-brand-navy-700 hover:shadow-lg">
                            <div class="flex items-start justify-between gap-3">
                                <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700">
                                    <x-ui.icon name="users" />
                                </span>
                                <span class="rounded-full px-3 py-1 text-xs font-extrabold {{ $statusClass }}">
                                    {{ $statusLabel }}
                                </span>
                            </div>

                            <h3 class="mt-4 text-lg font-black text-brand-navy-900">
                                {{ $student->full_name }}
                            </h3>

                            <dl class="mt-4 space-y-3 text-sm">
                                <div>
                                    <dt class="font-semibold text-muted">Student Number</dt>
                                    <dd class="mt-1 font-extrabold text-brand-navy-900">
                                        {{ $student->student_number ?: 'Not yet assigned' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-muted">Grade Level</dt>
                                    <dd class="mt-1 font-bold text-brand-navy-900">
                                        {{ $enrollment?->gradeLevel?->name ?? 'Not yet assigned' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-muted">Academic Year</dt>
                                    <dd class="mt-1 font-bold text-brand-navy-900">
                                        {{ $enrollment?->academicYear?->name ?? 'No enrollment record' }}
                                    </dd>
                                </div>
                            </dl>

                            <div class="mt-5 flex items-center justify-between border-t border-border pt-4 text-sm font-extrabold text-brand-navy-700">
                                <span>View enrollment progress</span>
                                <x-ui.icon name="arrow-right" size="size-4" class="transition group-hover:translate-x-1" />
                            </div>
                        </a>
                    @empty
                        <div class="jca-card p-6 sm:col-span-2 xl:col-span-3">
                            <x-ui.empty-state
                                icon="users"
                                title="No linked children"
                                description="Verified student records linked to your parent or guardian account will appear here." />
                        </div>
                    @endforelse
                </div>
            </section>
        @endisset

        @isset($adminData)
            @include('communications.portal._admin-dashboard-sections', $adminData)
        @endisset

        @isset($registrarData)
            @include('communications.portal._registrar-dashboard-sections', $registrarData)
        @endisset

        @isset($accountingData)
            @include('communications.portal._accounting-dashboard-sections', $accountingData)
        @endisset

        <section class="jca-card overflow-hidden" aria-labelledby="announcement-title">
            <div class="flex items-center justify-between gap-4 border-b border-border px-5 py-4 sm:px-6">
                <div>
                    <h2 id="announcement-title" class="text-lg font-black text-brand-navy-900">Latest announcements</h2>
                    <p class="mt-0.5 text-sm text-muted">School updates visible to your account.</p>
                </div>
                <a class="inline-flex items-center gap-2 text-sm font-bold text-brand-navy-700 hover:underline" href="{{ route('communications.index') }}">
                    View all
                    <x-ui.icon name="arrow-right" size="size-4" />
                </a>
            </div>

            <div class="divide-y divide-border">
                @forelse ($announcements as $announcement)
                    <a href="{{ route('communications.show', $announcement) }}" class="group flex gap-4 px-5 py-5 transition hover:bg-slate-50 sm:px-6">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-warning-100 text-warning-800">
                            <x-ui.icon name="announcement" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-bold text-brand-navy-900 group-hover:underline">{{ $announcement->title }}</span>
                            <span class="mt-1 block text-sm leading-6 text-muted">{{ \Illuminate\Support\Str::limit($announcement->body, 180) }}</span>
                        </span>
                        <x-ui.icon name="arrow-right" size="size-4" class="mt-3 text-slate-400" />
                    </a>
                @empty
                    <div class="p-5 sm:p-6">
                        <x-ui.empty-state icon="announcement" title="No announcements yet" description="New school announcements for your role will appear here." />
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>



