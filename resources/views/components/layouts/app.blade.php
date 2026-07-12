<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#29267d">
        <title>{{ isset($title) ? "{$title} | ".config('app.name') : config('app.name') }}</title>
        @if (! app()->environment('testing'))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="font-sans antialiased">
        <a href="#main-content" class="sr-only fixed left-4 top-4 z-50 rounded-md bg-brand-navy-900 px-4 py-3 font-bold text-white focus:not-sr-only">Skip to main content</a>

        <div class="jca-shell" x-data="{ navigationOpen: false }" @keydown.escape.window="navigationOpen = false">
            <header class="border-b border-border bg-white/95 backdrop-blur">
                <div class="mx-auto flex max-w-screen-2xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                    <x-brand-mark />
                    <button type="button" class="jca-button jca-button-secondary lg:hidden" @click="navigationOpen = true" :aria-expanded="navigationOpen.toString()" aria-controls="primary-navigation">Menu</button>
                    <div class="hidden items-center gap-3 lg:flex">
                        <span class="text-sm font-semibold text-muted">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('identity.logout') }}">
                            @csrf
                            <x-ui.button variant="secondary" type="submit">Sign out</x-ui.button>
                        </form>
                    </div>
                </div>
            </header>

            <div class="mx-auto flex max-w-screen-2xl">
                <div x-cloak x-show="navigationOpen" x-transition.opacity class="fixed inset-0 z-30 bg-brand-navy-900/35 lg:hidden" @click="navigationOpen = false" aria-hidden="true"></div>

                <aside id="primary-navigation" x-cloak x-show="navigationOpen" x-transition class="fixed inset-y-0 left-0 z-40 w-72 border-r border-border bg-white p-5 shadow-xl lg:static lg:block lg:min-h-[calc(100vh-77px)] lg:w-64 lg:translate-x-0 lg:shadow-none" :class="{ 'lg:block': navigationOpen }">
                    <div class="mb-6 flex items-center justify-between lg:hidden">
                        <span class="font-bold text-brand-navy-900">Navigation</span>
                        <button type="button" class="jca-button jca-button-secondary" @click="navigationOpen = false">Close</button>
                    </div>

                    <nav aria-label="Primary navigation" class="space-y-1">
                        <a href="{{ route('identity.dashboard') }}" class="block rounded-lg px-3 py-2 font-bold text-brand-navy-900 hover:bg-brand-blue-100">My account</a>
                        @if (auth()->user()->hasAnyRole(['admin', 'accounting', 'registrar', 'guardian', 'student']))
                            <a href="{{ route('billing.portal.index') }}" class="block rounded-lg px-3 py-2 font-bold text-brand-navy-900 hover:bg-brand-blue-100">Financial records</a>
                        @endif
                        @if (auth()->user()->hasAnyRole(['admin', 'accounting', 'registrar']))
                            <a href="{{ route('billing.index') }}" class="block rounded-lg px-3 py-2 font-bold text-brand-navy-900 hover:bg-brand-blue-100">Billing queue</a>
                        @endif
                        @if (auth()->user()->hasRole('admin'))
                            <a href="{{ route('billing.configuration.index') }}" class="block rounded-lg px-3 py-2 font-bold text-brand-navy-900 hover:bg-brand-blue-100">Financial configuration</a>
                        @endif
                        @if (auth()->user()->hasAnyRole(['admin', 'registrar']))
                            <a href="{{ route('enrollment.registrar.index') }}" class="block rounded-lg px-3 py-2 font-bold text-brand-navy-900 hover:bg-brand-blue-100">Enrollment review</a>
                            <a href="{{ route('section-assignment.index') }}" class="block rounded-lg px-3 py-2 font-bold text-brand-navy-900 hover:bg-brand-blue-100">Section assignment</a>
                            <a href="{{ route('support.queue') }}" class="block rounded-lg px-3 py-2 font-bold text-brand-navy-900 hover:bg-brand-blue-100">Restricted support review</a>
                            <a href="{{ route('academics.admin.index') }}" class="block rounded-lg px-3 py-2 font-bold text-brand-navy-900 hover:bg-brand-blue-100">Academics</a>
                        @endif
                        @if (auth()->user()->hasRole('guardian'))
                            <a href="{{ route('guardian.enrollment.progress') }}" class="block rounded-lg px-3 py-2 font-bold text-brand-navy-900 hover:bg-brand-blue-100">Enrollment progress</a>
                        @endif
                        @if (auth()->user()->hasRole('student'))
                            <a href="{{ route('student.enrollment.progress') }}" class="block rounded-lg px-3 py-2 font-bold text-brand-navy-900 hover:bg-brand-blue-100">My enrollment</a>
                        @endif
                        @if (auth()->user()->hasRole('teacher'))
                            <a href="{{ route('academics.teacher.assignments') }}" class="block rounded-lg px-3 py-2 font-bold text-brand-navy-900 hover:bg-brand-blue-100">My teaching</a>
                        @endif
                        @if (auth()->user()->hasRole('admin'))
                            <a href="{{ route('identity.admin.users.index') }}" class="block rounded-lg px-3 py-2 font-bold text-brand-navy-900 hover:bg-brand-blue-100">Users</a>
                            <a href="{{ route('people.admin.index') }}" class="block rounded-lg px-3 py-2 font-bold text-brand-navy-900 hover:bg-brand-blue-100">People</a>
                        @endif
                        <a href="{{ route('foundation.smoke') }}" class="block rounded-lg px-3 py-2 font-bold text-brand-navy-900 hover:bg-brand-blue-100">Foundation</a>
                    </nav>
                </aside>

                <main id="main-content" class="min-w-0 flex-1 px-4 py-8 sm:px-6 lg:px-10">
                    <x-flash />
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
