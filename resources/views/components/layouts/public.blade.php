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

        <div class="jca-shell">
            <header class="border-b border-border bg-white/95 backdrop-blur">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <x-brand-mark />
                    @auth
                        <a class="jca-button jca-button-secondary" href="{{ route('identity.dashboard') }}">My account</a>
                    @else
                        <a class="jca-button jca-button-secondary" href="{{ route('identity.login') }}">Sign in</a>
                    @endauth
                </div>
            </header>

            <main id="main-content" class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <x-flash />
                {{ $slot }}
            </main>

            <footer class="border-t border-border bg-white">
                <div class="mx-auto max-w-7xl px-4 py-6 text-sm text-muted sm:px-6 lg:px-8">
                    JCA Enrollment and Student Management System
                </div>
            </footer>
        </div>
    </body>
</html>
