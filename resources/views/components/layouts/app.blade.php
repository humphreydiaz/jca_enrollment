<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#29267d">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ isset($title) ? "{$title} | ".config('app.name') : config('app.name') }}</title>
        @if (! app()->environment('testing'))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="font-sans antialiased">
        <a href="#main-content" class="sr-only fixed left-4 top-4 z-50 rounded-md bg-brand-navy-900 px-4 py-3 font-bold text-white focus:not-sr-only">Skip to main content</a>

        @php($user = auth()->user())
        <div class="jca-shell" x-data="{ navigationOpen: false }" @keydown.escape.window="navigationOpen = false">
            <div x-cloak x-show="navigationOpen" x-transition.opacity class="fixed inset-0 z-40 bg-brand-navy-900/45 backdrop-blur-sm lg:hidden" @click="navigationOpen = false" aria-hidden="true"></div>

            <aside id="primary-navigation" x-cloak x-show="navigationOpen" x-transition class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-border bg-white shadow-2xl lg:!flex lg:shadow-none">
                <div class="flex min-h-20 items-center justify-between border-b border-border px-5">
                    <x-brand-mark />
                    <button type="button" class="grid size-10 place-items-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-brand-navy-900 lg:hidden" @click="navigationOpen = false" aria-label="Close navigation">
                        <x-ui.icon name="close" />
                    </button>
                </div>

                <nav aria-label="Primary navigation" class="flex-1 space-y-6 overflow-y-auto px-4 py-5">
                    <section aria-labelledby="navigation-overview">
                        <h2 id="navigation-overview" class="px-3 text-[0.68rem] font-extrabold uppercase tracking-[0.16em] text-slate-400">Overview</h2>
                        <div class="mt-2 space-y-1">
                            <x-ui.nav-link :href="route('identity.dashboard')" icon="dashboard" :active="request()->routeIs('identity.dashboard')">Dashboard</x-ui.nav-link>
                            <x-ui.nav-link :href="route('communications.index')" icon="announcement" :active="request()->routeIs('communications.index', 'communications.show')">Announcements</x-ui.nav-link>
                        </div>
                    </section>

                    @if ($user->hasAnyRole(['admin', 'registrar', 'guardian', 'student', 'teacher']))
                        <section aria-labelledby="navigation-school">
                            <h2 id="navigation-school" class="px-3 text-[0.68rem] font-extrabold uppercase tracking-[0.16em] text-slate-400">School records</h2>
                            <div class="mt-2 space-y-1">
                                @if ($user->hasAnyRole(['admin', 'registrar']))
                                    <x-ui.nav-link :href="route('enrollment.registrar.index')" icon="enrollment" :active="request()->routeIs('enrollment.registrar.*')">Enrollment review</x-ui.nav-link>
                                    <x-ui.nav-link :href="route('people.registrar.students.index')" icon="people" :active="request()->routeIs('people.registrar.students.*')">Student directory</x-ui.nav-link>
                                    <x-ui.nav-link :href="route('section-assignment.index')" icon="sections" :active="request()->routeIs('section-assignment.*', 'promotion.*')">Section assignment</x-ui.nav-link>
                                    <x-ui.nav-link :href="route('support.queue')" icon="support" :active="request()->routeIs('support.*')">Restricted support</x-ui.nav-link>
                                    <x-ui.nav-link :href="route('academics.admin.index')" icon="academics" :active="request()->routeIs('academics.admin.*')">Academics</x-ui.nav-link>
                                @endif
                                @if ($user->hasRole('guardian'))
                                    <x-ui.nav-link :href="route('guardian.enrollment.progress')" icon="progress" :active="request()->routeIs('guardian.enrollment.progress')">Enrollment progress</x-ui.nav-link>
                                    <x-ui.nav-link :href="route('communications.preferences.edit')" icon="message" :active="request()->routeIs('communications.preferences.*')">SMS preferences</x-ui.nav-link>
                                @endif
                                @if ($user->hasRole('student'))
                                    <x-ui.nav-link :href="route('student.enrollment.progress')" icon="progress" :active="request()->routeIs('student.enrollment.progress')">My enrollment</x-ui.nav-link>
                                @endif
                                @if ($user->hasRole('teacher'))
                                    <x-ui.nav-link :href="route('academics.teacher.assignments')" icon="teaching" :active="request()->routeIs('academics.teacher.*')">My teaching</x-ui.nav-link>
                                @endif
                            </div>
                        </section>
                    @endif

                    @if ($user->hasAnyRole(['admin', 'accounting', 'registrar', 'guardian', 'student']))
                        <section aria-labelledby="navigation-finance">
                            <h2 id="navigation-finance" class="px-3 text-[0.68rem] font-extrabold uppercase tracking-[0.16em] text-slate-400">Finance</h2>
                            <div class="mt-2 space-y-1">
                                <x-ui.nav-link :href="route('billing.portal.index')" icon="finance" :active="request()->routeIs('billing.portal.*', 'billing.print.*')">Financial records</x-ui.nav-link>
                                @if ($user->hasAnyRole(['admin', 'accounting', 'registrar']))
                                    <x-ui.nav-link :href="route('billing.index')" icon="billing" :active="request()->routeIs('billing.index', 'billing.assessments.*', 'billing.payments.*')">Billing queue</x-ui.nav-link>
                                @endif
                                @if ($user->hasRole('admin'))
                                    <x-ui.nav-link :href="route('billing.configuration.index')" icon="settings" :active="request()->routeIs('billing.configuration.*')">Financial configuration</x-ui.nav-link>
                                @endif
                            </div>
                        </section>
                    @endif

                    @if ($user->hasAnyRole(['admin', 'registrar', 'accounting', 'teacher']))
                        <section aria-labelledby="navigation-reporting">
                            <h2 id="navigation-reporting" class="px-3 text-[0.68rem] font-extrabold uppercase tracking-[0.16em] text-slate-400">Insights</h2>
                            <div class="mt-2 space-y-1">
                                <x-ui.nav-link :href="route('reporting.index')" icon="reports" :active="request()->routeIs('reporting.index', 'reporting.show', 'reporting.print')">Reports</x-ui.nav-link>
                            </div>
                        </section>
                    @endif

                    @if ($user->hasRole('admin'))
                        <section aria-labelledby="navigation-admin">
                            <h2 id="navigation-admin" class="px-3 text-[0.68rem] font-extrabold uppercase tracking-[0.16em] text-slate-400">Administration</h2>
                            <div class="mt-2 space-y-1">
                                <x-ui.nav-link :href="route('communications.admin')" icon="communications" :active="request()->routeIs('communications.admin')">Communications</x-ui.nav-link>
                                <x-ui.nav-link :href="route('identity.admin.users.index')" icon="users" :active="request()->routeIs('identity.admin.users.*')">Users</x-ui.nav-link>
                                <x-ui.nav-link :href="route('people.admin.index')" icon="people" :active="request()->routeIs('people.admin.*')">People</x-ui.nav-link>
                                <x-ui.nav-link :href="route('reporting.audit.index')" icon="audit" :active="request()->routeIs('reporting.audit.*')">Audit trail</x-ui.nav-link>
                            </div>
                        </section>
                    @endif
                </nav>

                <div class="border-t border-border p-4 lg:hidden">
                    <form method="POST" action="{{ route('identity.logout') }}">
                        @csrf
                        <button type="submit" class="jca-button-secondary w-full"><x-ui.icon name="logout" /> Sign out</button>
                    </form>
                </div>
            </aside>

            <div class="min-h-screen lg:pl-72">
                <header class="sticky top-0 z-30 border-b border-brand-navy-700 bg-brand-navy-900/95 text-white shadow-sm backdrop-blur">
                    <div class="flex min-h-20 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                        <div class="flex min-w-0 items-center gap-3">
                            <button type="button" class="grid size-11 shrink-0 place-items-center rounded-xl border border-white/20 bg-white/10 text-white shadow-sm hover:bg-white/15 lg:hidden" @click="navigationOpen = true" :aria-expanded="navigationOpen.toString()" aria-controls="primary-navigation" aria-label="Open navigation">
                                <x-ui.icon name="menu" />
                            </button>
                            <div class="lg:hidden"><x-brand-mark inverse /></div>
                            <div class="hidden lg:block">
                                <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-300">JCA workspace</p>
                                <p class="truncate text-lg font-extrabold text-white">{{ $title ?? 'Dashboard' }}</p>
                            </div>
                        </div>
                        <div class="hidden items-center gap-3 sm:flex">
                            <span class="grid size-10 place-items-center rounded-full bg-brand-gold-500 text-sm font-black text-brand-navy-900" aria-hidden="true">{{ str($user->name)->substr(0, 1)->upper() }}</span>
                            <div class="hidden text-right md:block">
                                <p class="text-sm font-bold text-white">{{ $user->name }}</p>
                                <p class="text-xs text-slate-300">{{ $user->roles->pluck('name')->join(', ') }}</p>
                            </div>
                            <form method="POST" action="{{ route('identity.logout') }}">
                                @csrf
                                <button type="submit" class="jca-button-secondary" aria-label="Sign out"><x-ui.icon name="logout" /> <span class="hidden xl:inline">Sign out</span></button>
                            </form>
                        </div>
                    </div>
                </header>

                <main id="main-content" class="min-w-0 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mx-auto max-w-screen-2xl">
                        <x-flash />
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
