<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#29267d">
        <meta name="jca-ui-version" content="uxui-patch-3">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ isset($title) ? "{$title} | ".config('app.name') : config('app.name') }}</title>
        @if (! app()->environment('testing'))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body @class(['m-0 p-0 font-sans antialiased', 'has-print-slip' => isset($printSlip)])>
        <a href="#main-content" class="sr-only fixed left-4 top-4 z-50 rounded-md bg-brand-navy-900 px-4 py-3 font-bold text-white focus:not-sr-only">Skip to main content</a>

        {{-- Database-backed sidebar indicators; refresh on page load. --}}
        @php
            $user = auth()->user();
            $enrollmentNeedsReview = false;
            $billingNeedsReview = false;
            $gradeNeedsReview = false;

           $teacherGradesNeedAction = false;

            if ($user && $user->hasRole('teacher')) {
                $gradebook = app(\App\Modules\Grading\Queries\TeacherGradebookQuery::class)
                    ->forUser($user, null);

                $teacherGradesNeedAction =
                    ($gradebook['totals']['toFinish'] ?? 0) > 0
                    || ($gradebook['totals']['toSubmit'] ?? 0) > 0;
            }

            if ($user->hasAnyRole(['admin', 'registrar'])) {
                $enrollmentNeedsReview = \Illuminate\Support\Facades\DB::table('enrollments')
                    ->whereIn('status', ['submitted', 'under_review'])
                    ->exists();

                $gradeNeedsReview = \Illuminate\Support\Facades\DB::table('student_subject_grades')
                    ->where('status', 'submitted')
                    ->exists()
                    || \Illuminate\Support\Facades\DB::table('grade_correction_requests')
                        ->where('status', 'pending')
                        ->exists();
            }

            if ($user->hasAnyRole(['admin', 'accounting', 'registrar'])) {
                $billingNeedsReview = \Illuminate\Support\Facades\DB::table('payments')
                    ->where('status', 'draft')
                    ->whereExists(function ($query) {
                        $query->selectRaw('1')
                            ->from('payment_evidence')
                            ->whereColumn('payment_evidence.payment_id', 'payments.id');
                    })
                    ->exists();
            }
    $notificationSeenAt = \Illuminate\Support\Facades\DB::table('sidebar_notification_reads')
        ->where('user_id', $user->id)
        ->pluck('last_seen_at', 'notification_key');

    $profileUpdatesNeedReview = false;

    if ($user->hasAnyRole(['admin', 'registrar'])) {
        $profileUpdatesNeedReview = \Illuminate\Support\Facades\DB::table('audit_logs')
            ->whereIn('action', [
                'people.student.self_profile_updated',
                'people.guardian.self_profile_updated',
            ])
            ->where('occurred_at', '>', $notificationSeenAt->get('profile_updates') ?? '1970-01-01')
            ->exists();
    }
    $enrollmentNeedsReview = $enrollmentNeedsReview
        && \Illuminate\Support\Facades\DB::table('enrollments')
            ->whereIn('status', ['submitted', 'under_review'])
            ->where('updated_at', '>', $notificationSeenAt->get('enrollment_review') ?? '1970-01-01')
            ->exists();

    $billingNeedsReview = $billingNeedsReview
        && \Illuminate\Support\Facades\DB::table('payments')
            ->where('status', 'draft')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('payment_evidence')
                    ->whereColumn('payment_evidence.payment_id', 'payments.id');
            })
            ->where('updated_at', '>', $notificationSeenAt->get('billing_queue') ?? '1970-01-01')
            ->exists();

    $gradeNeedsReview = ($gradeNeedsReview ?? false)
        && (
            \Illuminate\Support\Facades\DB::table('student_subject_grades')
                ->where('status', 'submitted')
                ->where('updated_at', '>', $notificationSeenAt->get('grade_approval') ?? '1970-01-01')
                ->exists()
            ||
            \Illuminate\Support\Facades\DB::table('grade_correction_requests')
                ->where('status', 'pending')
                ->where('updated_at', '>', $notificationSeenAt->get('grade_approval') ?? '1970-01-01')
                ->exists()
        );
    $familyLinksNeedsReview = $user->hasAnyRole(['admin', 'registrar'])
        && \Illuminate\Support\Facades\DB::table('guardian_student')
            ->whereNull('verified_at')
            ->whereNull('ended_at')
            ->where('created_at', '>', $notificationSeenAt->get('family_links') ?? '1970-01-01')
            ->exists();

    $sectionNeedsReview = $user->hasAnyRole(['admin', 'registrar'])
        && \Illuminate\Support\Facades\DB::table('enrollments')
            ->whereIn('status', ['approved', 'enrolled'])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('section_assignments')
                    ->whereColumn('section_assignments.enrollment_id', 'enrollments.id')
                    ->where('section_assignments.status', 'confirmed')
                    ->whereNull('section_assignments.ended_at');
            })
            ->where('updated_at', '>', $notificationSeenAt->get('section_assignment') ?? '1970-01-01')
            ->exists();

    $supportNeedsReview = $user->hasAnyRole(['admin', 'registrar'])
        && \Illuminate\Support\Facades\DB::table('support_cases')
            ->where('status', 'open')
            ->where('updated_at', '>', $notificationSeenAt->get('restricted_support') ?? '1970-01-01')
            ->exists();
@endphp
        @php
            /*
             * Role-scoped navigation. Every group/item below is rendered only for the
             * roles that could already reach it; hiding an item is never authorization
             * (routes, policies, and scoped queries still decide access).
             */
            $navItem = fn (string $label, string $route, string $icon, array $active, array $extra = []) => array_merge([
                'label' => $label,
                'route' => $route,
                'params' => [],
                'icon' => $icon,
                'active' => $active,
                'notification' => false,
                'notificationKey' => null,
            ], $extra);

            $navGroups = [];

            $navGroups[] = ['label' => 'Overview', 'items' => [
                $navItem('Dashboard', 'identity.dashboard', 'dashboard', ['identity.dashboard']),
                $navItem('Announcements', 'communications.index', 'announcement', ['communications.index', 'communications.show']),
            ]];

            if ($user->hasRole('guardian')) {
                $navGroups[] = ['label' => 'My children', 'items' => [
                    $navItem('Enrollment progress', 'guardian.enrollment.progress', 'progress', ['guardian.enrollment.progress']),
                    $navItem('Student information', 'my-information.index', 'people', ['my-information.*']),
                    $navItem('Final grades', 'grading.family.index', 'academics', ['grading.family.*']),
                ]];
            }

            if ($user->hasRole('student')) {
                $navGroups[] = ['label' => 'My school records', 'items' => [
                    $navItem('My enrollment', 'student.enrollment.progress', 'progress', ['student.enrollment.progress']),
                    $navItem('Student information', 'my-information.index', 'people', ['my-information.*']),
                    $navItem('Final grades', 'grading.family.index', 'academics', ['grading.family.*']),
                ]];
            }

            if ($user->hasRole('teacher')) {
                $navGroups[] = ['label' => 'My teaching', 'items' => [
                    $navItem('My classes', 'academics.teacher.assignments', 'teaching', ['academics.teacher.*', 'billing.teacher.*', 'teacher.students.*']),
                    $navItem('Grades', 'grading.teacher.index', 'academics', ['grading.teacher.*'], ['notification' => $teacherGradesNeedAction, 'notificationKey' => 'teacher_grades']),
                ]];
            }

            if ($user->hasAnyRole(['admin', 'registrar'])) {
                $studentItems = [
                    $navItem('Enrollment review', 'enrollment.registrar.index', 'enrollment', ['enrollment.registrar.*'], ['notification' => $enrollmentNeedsReview, 'notificationKey' => 'enrollment_review']),
                    $navItem('Student directory', 'people.registrar.students.index', 'people', ['people.registrar.students.*']),
                    $navItem('Family links', 'people.admin.index', 'users', ['people.admin.*', 'people.family.*'], ['notification' => $familyLinksNeedsReview, 'notificationKey' => 'family_links']),
                    $navItem('Section assignment', 'section-assignment.index', 'sections', ['section-assignment.*', 'promotion.*'], ['notification' => $sectionNeedsReview, 'notificationKey' => 'section_assignment']),
                ];

                $studentItems[] = $navItem('Information updates', 'reporting.information-updates.index', 'audit', ['reporting.information-updates.*'], ['notification' => $profileUpdatesNeedReview, 'notificationKey' => 'profile_updates']);

                $navGroups[] = ['label' => 'School records', 'items' => $studentItems];
                $navGroups[] = ['label' => 'Academics & support', 'items' => [
                    $navItem('Academics', 'academics.admin.index', 'academics', ['academics.admin.*']),
                    $navItem('Grade approval', 'grading.admin.index', 'check-all', ['grading.admin.*'], ['notification' => $gradeNeedsReview, 'notificationKey' => 'grade_approval']),
                    $navItem('Restricted support', 'support.queue', 'support', ['support.*'], ['notification' => $supportNeedsReview, 'notificationKey' => 'restricted_support']),
                ]];
            }

            // Financial records are staff-only. Students and guardians do not see
            // financial records (panel decision; routes are restricted as well).
            if ($user->hasAnyRole(['admin', 'accounting', 'registrar'])) {
                $financeItems = [
                    $navItem('Financial records', 'billing.portal.index', 'finance', ['billing.portal.*', 'billing.print.*']),
                    $navItem('Billing queue', 'billing.index', 'billing', ['billing.index', 'billing.assessments.*', 'billing.payments.*'], ['notification' => $billingNeedsReview, 'notificationKey' => 'billing_queue']),
                ];

                if ($user->hasAnyRole(['admin', 'accounting'])) {
                    $financeItems[] = $navItem('Financial configuration', 'billing.configuration.index', 'settings', ['billing.configuration.*']);
                }

                $navGroups[] = ['label' => 'Finance', 'items' => $financeItems];
            }

            if ($user->hasAnyRole(['admin', 'registrar', 'accounting', 'teacher'])) {
                $navGroups[] = ['label' => 'Insights', 'items' => [
                    $navItem('Reports', 'reporting.index', 'reports', ['reporting.index', 'reporting.show', 'reporting.print']),
                ]];
            }

            if ($user->hasRole('admin')) {
                $navGroups[] = ['label' => 'Administration', 'items' => [
                    $navItem('Communications', 'communications.admin', 'communications', ['communications.admin']),
                    $navItem('Users', 'identity.admin.users.index', 'users', ['identity.admin.users.*']),
                    $navItem('Audit trail', 'reporting.audit.index', 'audit', ['reporting.audit.*']),
                    $navItem('Backups', 'operations.backups.index', 'settings', ['operations.backups.*']),
                ]];
            }

            $navGroups[] = ['label' => 'My account', 'items' => [
                $navItem('SMS preferences', 'communications.preferences.edit', 'message', ['communications.preferences.*']),
            ]];

            // Drop any item whose route is not registered so a missing module never breaks the shell.
            $navGroups = collect($navGroups)
                ->map(function (array $group) {
                    $group['items'] = array_values(array_filter(
                        $group['items'],
                        fn (array $item) => \Illuminate\Support\Facades\Route::has($item['route']),
                    ));

                    return $group;
                })
                ->filter(fn (array $group) => $group['items'] !== [])
                ->values();

            // Locate the current module for the header and breadcrumb.
            $activeNav = null;
            foreach ($navGroups as $group) {
                foreach ($group['items'] as $item) {
                    if ($activeNav === null && request()->routeIs(...$item['active'])) {
                        $activeNav = ['group' => $group['label'], 'item' => $item];
                    }
                }
            }

            $backDestination = new \App\Modules\Shared\Navigation\BackDestination(request());
            $isRootPage = $backDestination->isRoot();
            $backUrl = $isRootPage ? null : $backDestination->url();

            $pageTitle = (string) ($title ?? 'Dashboard');

            // Explorer-style location bar, built from the declared page hierarchy.
            $moduleLabels = [];
            foreach ($navGroups as $group) {
                foreach ($group['items'] as $item) {
                    $moduleLabels[$item['route']] ??= $item['label'];
                }
            }
            $breadcrumbs = \App\Modules\Shared\Navigation\BreadcrumbTrail::build(
                request()->route()?->getName(),
                $user,
                $moduleLabels,
                $pageTitle,
                $activeNav['item']['route'] ?? null,
            );

            // Back falls back to the breadcrumb parent, so both controls agree.
            $parentCrumb = collect($breadcrumbs)->reverse()->first(fn ($crumb) => $crumb['url'] !== null);
            if (! $isRootPage && $parentCrumb) {
                $backUrl = $parentCrumb['url'];
            }
        @endphp

        <div class="jca-shell" x-data="{ navigationOpen: false }" @keydown.escape.window="navigationOpen = false">
            <div x-cloak x-show="navigationOpen" x-transition.opacity class="fixed inset-0 z-40 bg-brand-navy-900/45 backdrop-blur-sm lg:hidden" @click="navigationOpen = false" aria-hidden="true"></div>

            <aside id="primary-navigation" x-show="navigationOpen" x-transition class="jca-sidebar fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-border bg-white shadow-2xl lg:!flex lg:shadow-none">
                <div class="flex min-h-20 items-center justify-between border-b border-border px-5">
                    <x-brand-mark />
                    <button type="button" class="grid size-10 place-items-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-brand-navy-900 lg:hidden" @click="navigationOpen = false" aria-label="Close navigation">
                        <x-ui.icon name="close" />
                    </button>
                </div>

                <nav aria-label="Primary navigation" class="flex-1 space-y-5 overflow-y-auto px-4 py-5">
                    @foreach ($navGroups as $groupIndex => $group)
                        <section aria-labelledby="navigation-group-{{ $groupIndex }}">
                            <h2 id="navigation-group-{{ $groupIndex }}" class="jca-nav-heading">{{ $group['label'] }}</h2>
                            <div class="mt-1.5 space-y-1">
                                @foreach ($group['items'] as $item)
                                    <x-ui.nav-link
                                        :href="route($item['route'], $item['params'])"
                                        :icon="$item['icon']"
                                        :active="request()->routeIs(...$item['active'])"
                                        :notification="$item['notification']"
                                        :notification-key="$item['notificationKey']"
                                        data-jca-nav-root
                                    >{{ $item['label'] }}</x-ui.nav-link>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </nav>

                <div class="border-t border-border p-4 lg:hidden">
                    <form method="POST" action="{{ route('identity.logout') }}" data-jca-signout>
                        @csrf
                        <button type="submit" class="jca-button-secondary w-full"><x-ui.icon name="logout" /> Sign out</button>
                    </form>
                </div>
            </aside>

            <div class="min-h-screen lg:pl-72">
                <header class="fixed inset-x-0 top-0 z-30 border-b border-brand-navy-700 bg-brand-navy-900 text-white shadow-sm lg:left-72">
                    <div class="flex min-h-20 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                        <div class="flex min-w-0 items-center gap-3">
                            <button type="button" class="grid size-11 shrink-0 place-items-center rounded-xl border border-white/20 bg-white/10 text-white shadow-sm hover:bg-white/15 lg:hidden" @click="navigationOpen = true" :aria-expanded="navigationOpen.toString()" aria-controls="primary-navigation" aria-label="Open navigation">
                                <x-ui.icon name="menu" />
                            </button>
                            <div class="lg:hidden"><x-brand-mark inverse /></div>
                            <div class="hidden min-w-0 lg:block">
                                <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-300">{{ $activeNav['group'] ?? 'JCA workspace' }}</p>
                                <p class="truncate text-xl font-extrabold text-white">{{ $pageTitle }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="hidden size-10 place-items-center rounded-full bg-brand-gold-500 text-sm font-black text-brand-navy-900 sm:grid" aria-hidden="true">{{ str($user->name)->substr(0, 1)->upper() }}</span>
                            <div class="hidden text-right md:block">
                                <p class="text-sm font-bold text-white">{{ $user->name }}</p>
                                <p class="text-sm text-slate-300">{{ $user->roles->pluck('name')->map(fn ($role) => str($role)->headline())->join(', ') }}</p>
                            </div>
                            <form method="POST" action="{{ route('identity.logout') }}" class="hidden sm:block" data-jca-signout>
                                @csrf
                                <button type="submit" class="jca-button-secondary" aria-label="Sign out"><x-ui.icon name="logout" /> <span class="hidden xl:inline">Sign out</span></button>
                            </form>
                        </div>
                    </div>
                </header>

                <main id="main-content" class="jca-main min-w-0 px-4 pb-8 sm:px-6 lg:px-8" @if ($isRootPage) data-jca-page-root @endif data-jca-page>
                    {{-- Location bar (Windows File Explorer style): Back + Home > ... > [current page]. --}}
                    <div class="jca-location print:hidden" data-jca-location>
                        @unless ($isRootPage)
                            <a href="{{ $backUrl }}" class="jca-location__back" data-jca-back data-fallback-url="{{ $backUrl }}" title="Back" aria-label="Back">
                                <x-ui.icon name="arrow-right" size="size-5" class="rotate-180" />
                                <span class="sr-only" data-jca-back-label>Back</span>
                            </a>
                        @endunless

                        <nav aria-label="Breadcrumb" class="jca-location__bar" data-jca-location-bar>
                            <ol class="jca-location__list">
                                @foreach ($breadcrumbs as $crumb)
                                    <li class="jca-location__item">
                                        @unless ($loop->first)
                                            <svg class="jca-location__chevron" width="16" height="16" style="width:1rem;height:1rem;flex-shrink:0" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M6 3.5 10.5 8 6 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        @endunless
                                        @if ($crumb['current'])
                                            <span class="jca-location__crumb jca-location__crumb--current" aria-current="page">
                                                @if ($crumb['home'])
                                                    <x-ui.icon name="dashboard" size="size-4" />
                                                @endif
                                                {{ $crumb['label'] }}
                                            </span>
                                        @else
                                            <a href="{{ $crumb['url'] }}" class="jca-location__crumb" data-jca-restore>
                                                @if ($crumb['home'])
                                                    <x-ui.icon name="dashboard" size="size-4" />
                                                @endif
                                                {{ $crumb['label'] }}
                                            </a>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </nav>
                    </div>

                    <x-flash />
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- Optional print-only slip: when a page provides one, printing (button or Ctrl+P) outputs only the slip. --}}
        @isset($printSlip)
            <style>
                .print-slip { display: none; }
                @media print {
                    @page { size: A4 portrait; margin: 12mm; }
                    html:has(body.has-print-slip), body.has-print-slip { background: #fff !important; min-height: 0 !important; }
                    body.has-print-slip > :not(.print-slip) { display: none !important; }
                    body.has-print-slip > .print-slip { display: block !important; color: #111; font-family: ui-sans-serif, system-ui, sans-serif; }
                }


            </style>
            <div class="print-slip" aria-hidden="true">{{ $printSlip }}</div>
        @endisset
    </body>
</html>



