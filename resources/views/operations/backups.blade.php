<x-layouts.app title="Backups">
    @php
        $rows = $backups->map(fn (array $backup) => [
            'name' => $backup['name'],
            'created' => $backup['created_at']->timezone(config('app.timezone'))->format('d M Y, g:i A'),
        ])->values();
        // After a refused confirmation (wrong password, etc.) the pop-up opens again for the same backup.
        $reopen = $rows->firstWhere('name', old('backup'));
    @endphp
    <div
        class="mx-auto max-w-5xl space-y-6"
        x-data="{
            restoreTarget: null,
            openRestore(target) { this.restoreTarget = target; this.$nextTick(() => this.$refs.restoreDialog.showModal()); },
            closeRestore() { this.$refs.restoreDialog.close(); this.restoreTarget = null; },
        }"
        @if ($reopen) x-init="openRestore(@js($reopen))" @endif
    >
        <header>
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-brand-orange-500">Administration</p>
            <h1 class="mt-2 text-3xl font-black text-brand-navy-900">Backups</h1>
            <p class="mt-2 text-sm leading-6 text-muted">Each backup is one encrypted file holding the whole database and every private document (requirements and payment evidence). Backups are made only when you click Back up now (and automatically just before a restore, so a restore can be undone). The newest {{ $keep }} are kept on the server and older ones are removed. Make a backup before big changes and at least once a week, and download a copy to keep off the server, for example on the school's encrypted drive.</p>
        </header>

        @error('backup')<p class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-brand-red-600" role="alert">{{ $message }}</p>@enderror
        @error('restore')<p class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-brand-red-600" role="alert">{{ $message }}</p>@enderror

        <section class="jca-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="backup-status-title">
            <div>
                <h2 id="backup-status-title" class="text-sm font-extrabold uppercase tracking-wider text-brand-navy-700">Last successful backup</h2>
                @if ($lastSuccess)
                    <p class="mt-1 text-lg font-black text-brand-navy-900">{{ $lastSuccess->timezone(config('app.timezone'))->format('d M Y, g:i A') }}</p>
                @else
                    <p class="mt-1 text-lg font-black text-brand-navy-900">No backup yet</p>
                @endif
                <div class="mt-2">
                    @if ($lastSuccess === null)
                        <x-ui.status-badge status="overdue" label="Reminder: no backup has been made yet" />
                    @elseif ($overdue)
                        <x-ui.status-badge status="overdue" label="Reminder: the newest backup is more than {{ $remindAfterDays }} days old" />
                    @else
                        <x-ui.status-badge status="active" label="Recent backup on file" />
                    @endif
                </div>
                @if (! empty($status['last_error']))
                    <p class="mt-2 text-sm text-brand-red-600">Last failure ({{ \Illuminate\Support\Carbon::parse($status['last_error_at'])->timezone(config('app.timezone'))->format('d M Y, g:i A') }}): {{ $status['last_error'] }}</p>
                @endif
            </div>
            <form method="POST" action="{{ route('operations.backups.store') }}">
                @csrf
                <x-ui.button type="submit">Back up now</x-ui.button>
            </form>
        </section>

        <section class="jca-card overflow-hidden" aria-labelledby="backup-files-title">
            <h2 id="backup-files-title" class="px-5 pt-5 text-lg font-black text-brand-navy-900">Stored backups</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="bg-brand-blue-100"><th scope="col" class="p-3">File</th><th scope="col" class="p-3">Created</th><th scope="col" class="p-3">Size</th><th scope="col" class="p-3"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                        @forelse ($backups as $index => $backup)
                            <tr class="border-b border-border">
                                <td class="p-3 font-mono text-xs">{{ $backup['name'] }}</td>
                                <td class="whitespace-nowrap p-3">{{ $rows[$index]['created'] }}</td>
                                <td class="whitespace-nowrap p-3">{{ \Illuminate\Support\Number::fileSize($backup['size'], 1) }}</td>
                                <td class="whitespace-nowrap p-3 text-right">
                                    <a class="font-bold text-brand-navy-700 underline underline-offset-2" href="{{ route('operations.backups.download', $backup['name']) }}">Download</a>
                                    <a class="ml-4 font-bold text-brand-red-600 underline underline-offset-2" href="{{ route('operations.backups.restore.confirm', $backup['name']) }}"
                                       aria-haspopup="dialog" @click.prevent="openRestore(@js($rows[$index]))">Restore</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-6 text-muted">No backups are stored yet. Click Back up now to make the first one.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="jca-card p-5 text-sm leading-6 text-muted" aria-labelledby="restore-title">
            <h2 id="restore-title" class="text-lg font-black text-brand-navy-900">Restoring</h2>
            <p class="mt-1">Restore puts every record and document back to the moment the backup was made. A safety backup of the current state is taken first (it appears in this list as "pre-restore", so a restore can be undone), the documents in place are moved aside rather than deleted, and the system puts itself back automatically if anything fails. Everyone signs in again afterwards. The files are encrypted with this system's key (APP_KEY): keep a copy of that key somewhere safe and separate from the backups.</p>
        </section>

        <dialog
            x-ref="restoreDialog"
            @close="restoreTarget = null"
            @click.self="closeRestore()"
            class="m-auto w-[min(40rem,calc(100%-2rem))] max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl border-0 bg-white p-0 text-ink shadow-2xl backdrop:bg-slate-950/55"
            aria-labelledby="restore-dialog-title"
        >
            <template x-if="restoreTarget">
                <div>
                    <header class="flex items-start justify-between gap-4 border-b border-border px-5 py-5 sm:px-6">
                        <div>
                            <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-red-600">Restore backup</p>
                            <h2 id="restore-dialog-title" class="mt-1 text-xl font-black text-brand-navy-900">Are you sure you want to restore?</h2>
                        </div>
                        <button type="button" class="grid size-10 shrink-0 place-items-center rounded-lg text-muted hover:bg-slate-100 hover:text-ink" @click="closeRestore()" aria-label="Close">
                            <x-ui.icon name="close" />
                        </button>
                    </header>
                    <div class="p-5 sm:p-6">
                        @include('operations.partials.restore-form', [
                            'alpineAction' => true,
                            'action' => "'".url('/admin/backups')."/' + restoreTarget.name + '/restore'",
                            'nameLabel' => '',
                            'createdLabel' => '',
                            'cancel' => new \Illuminate\Support\HtmlString('<button type="button" class="jca-button-secondary" @click="closeRestore()">Cancel</button>'),
                        ])
                    </div>
                </div>
            </template>
        </dialog>
    </div>
</x-layouts.app>
