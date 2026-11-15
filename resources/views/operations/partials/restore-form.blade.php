{{-- D-060: confirmation for restoring a backup over the live system. Used in the pop-up and the no-JavaScript page.
     $action: form URL (may be an Alpine binding when $alpineAction is true); $nameLabel/$createdLabel: what is restored. --}}
<form method="POST" @if ($alpineAction ?? false) :action="{{ $action }}" @else action="{{ $action }}" @endif class="space-y-5" x-data="{ typed: '', agreed: @js((bool) old('acknowledge')), busy: false }" @submit="busy = true">
    @csrf
    <input type="hidden" name="backup" @if ($alpineAction ?? false) :value="restoreTarget?.name" @else value="{{ $nameLabel }}" @endif>

    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-ink" role="alert">
        <p class="font-black text-brand-red-600">This replaces everything in the system with the backup.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li>Every record (enrollments, payments, grades, accounts, and the rest) goes back to how it was when the backup was made: <strong @if ($alpineAction ?? false) x-text="restoreTarget?.created" @endif>{{ $createdLabel }}</strong>. Anything entered after that is no longer shown.</li>
            <li>Uploaded documents go back to that time too. Today's documents are moved aside on the server, not deleted.</li>
            <li>A safety backup of the system as it is right now is made first, so this restore can be undone by restoring that safety backup.</li>
            <li>The site is unavailable to everyone while it runs (usually under a minute), and everyone, including you, has to sign in again afterwards.</li>
            <li>If anything goes wrong, the system puts itself back as it was automatically.</li>
        </ul>
        <p class="mt-2 font-mono text-xs text-muted" @if ($alpineAction ?? false) x-text="restoreTarget?.name" @endif>{{ $nameLabel }}</p>
    </div>

    <label class="flex items-start gap-3 text-sm font-semibold text-ink">
        <input type="checkbox" name="acknowledge" value="1" required class="mt-1" x-model="agreed" @checked(old('acknowledge'))>
        <span>I understand that every record and document will be replaced with this backup.</span>
    </label>
    @error('acknowledge')<p class="text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror

    <x-ui.field for="restore-confirmation" label="Type RESTORE to confirm *">
        <input id="restore-confirmation" name="confirmation" required autocomplete="off" spellcheck="false" class="jca-input font-mono" x-model="typed">
    </x-ui.field>
    @error('confirmation')<p class="-mt-3 text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror

    <x-ui.field for="restore-password" label="Your password *" hint="Your Admin password, to confirm it is you.">
        <input id="restore-password" type="password" name="password" required autocomplete="current-password" class="jca-input">
    </x-ui.field>
    @error('password')<p class="-mt-3 text-sm font-semibold text-brand-red-600">{{ $message }}</p>@enderror

    <div class="flex flex-wrap items-center justify-end gap-3 border-t border-border pt-4">
        {{ $cancel ?? '' }}
        <button type="submit" class="jca-button-danger" :disabled="busy || typed !== 'RESTORE' || ! agreed">
            <span x-show="! busy">Restore this backup</span>
            <span x-show="busy" x-cloak>Restoring… do not close this page</span>
        </button>
    </div>
</form>
