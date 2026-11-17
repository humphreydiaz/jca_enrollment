<x-layouts.app title="Restore backup">
    <div class="mx-auto max-w-2xl space-y-6">
        <header>
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-orange-500">Backups</p>
            <h1 class="mt-2 text-3xl font-black text-brand-navy-900">Restore this backup?</h1>
        </header>
        <section class="jca-card p-5 sm:p-6">
            @include('operations.partials.restore-form', [
                'action' => route('operations.backups.restore', $backup['name']),
                'nameLabel' => $backup['name'],
                'createdLabel' => $backup['created_at']->timezone(config('app.timezone'))->format('d M Y, g:i A'),
            ])
        </section>
        <a class="text-sm font-bold text-brand-navy-700 underline underline-offset-2" href="{{ route('operations.backups.index') }}">Cancel and go back to Backups</a>
    </div>
</x-layouts.app>
