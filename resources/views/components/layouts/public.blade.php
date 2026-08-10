@props(['fullWidth' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#29267d">
        @if ($fullWidth)
            <meta name="description" content="JCSGO Christian Academy R1-Inc. Student Enrollment and Management System — a modern, secure portal for families and school staff in Lingayen, Pangasinan.">
        @endif
        <title>{{ isset($title) ? "{$title} | ".config('app.name') : config('app.name') }}</title>
        @if (! app()->environment('testing'))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="font-sans antialiased">
        <a href="#main-content" class="sr-only fixed left-4 top-4 z-50 rounded-md bg-brand-navy-900 px-4 py-3 font-bold text-white focus:not-sr-only">Skip to main content</a>

        <div class="jca-shell flex min-h-screen flex-col">
            @if ($fullWidth)
                {{-- Landing page: transparent floating header over the hero --}}
                <header class="landing-header" id="landing-header">
                    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                        <x-brand-mark :inverse="true" />
                        @auth
                            <a class="jca-button jca-button-secondary" href="{{ route('identity.dashboard') }}">My account</a>
                        @else
                            <div class="flex items-center gap-3">
                                <a class="landing-header__link" href="{{ route('identity.login') }}">Sign in</a>
                            </div>
                        @endauth
                    </div>
                </header>
            @else
                <header class="shrink-0 border-b border-border bg-white/95 backdrop-blur">
                    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                        <x-brand-mark />
                        @auth
                            <a class="jca-button jca-button-secondary" href="{{ route('identity.dashboard') }}">My account</a>
                        @else
                            @if (request()->routeIs('identity.staff.*'))
                                <a class="jca-button jca-button-secondary" href="{{ route('identity.staff.login') }}">Staff sign in</a>
                            @else
                                <a class="jca-button jca-button-secondary" href="{{ route('identity.login') }}">Sign in</a>
                            @endif
                        @endauth
                    </div>
                </header>
            @endif

            <main id="main-content" @class([
                'flex-1',
                'mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8' => !$fullWidth,
            ])>
                <x-flash />
                {{ $slot }}
            </main>

            <footer class="shrink-0 border-t border-border bg-brand-navy-900">
                <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-between">
                        <div class="flex items-center gap-3">
                            <img src="{{ asset('images/logo/jca_logo.png') }}" alt="" aria-hidden="true" class="size-9 object-contain">
                            <span class="text-sm font-bold text-white/90">JCSGO Christian Academy R1-Inc.</span>
                        </div>
                        <p class="text-xs text-white/50">
                            Student Management System &middot; Lingayen, Pangasinan &middot; &copy; {{ date('Y') }}
                        </p>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
