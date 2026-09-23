<section data-section="templates" class="jca-card p-5 sm:p-6" aria-labelledby="template-title">
    <h2 id="template-title" class="text-xl font-black text-brand-navy-900">Create SMS template version</h2>
    <p class="mt-1 text-sm text-muted">Saving creates a new immutable version. Activating it deactivates older versions with the same code.</p>

    <form id="template-create-form" class="mt-5 space-y-4" method="POST" action="{{ route('communications.templates.store') }}" data-ajax>
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field for="template_code" label="Template code" hint="Letters, numbers, dashes, and underscores only.">
                <input id="template_code" name="code" type="text" maxlength="80" required value="{{ old('code') }}" class="jca-input" autocomplete="off">
            </x-ui.field>
            <x-ui.field for="template_purpose" label="Purpose">
                <select id="template_purpose" name="purpose" class="jca-input" required>
                    <option value="transactional" @selected(old('purpose', 'transactional') === 'transactional')>Transactional</option>
                    <option value="announcement" @selected(old('purpose') === 'announcement')>Announcement</option>
                </select>
            </x-ui.field>
        </div>

        <x-ui.field for="template_content" label="SMS content" hint="Allowed placeholders: school_name, status, action_url, announcement_title, announcement_body.">
            <textarea id="template_content" name="content" class="jca-input min-h-28" rows="4" maxlength="480" placeholder="Example: JCA enrollment status updated. Open the portal for details." required>{{ old('content') }}</textarea>
        </x-ui.field>

        <input type="hidden" name="is_active" value="0">
        <label class="flex items-start gap-3 rounded-xl border border-border p-3 text-sm">
            <input type="checkbox" name="is_active" value="1" class="mt-1" @checked(old('is_active') === '1')>
            <span><span class="block font-bold text-brand-navy-900">Activate this version</span><span class="text-muted">Use it for future queued messages after an authorized workflow selects it.</span></span>
        </label>

        <x-ui.button type="submit">Save template version</x-ui.button>
    </form>

    <div class="mt-6 border-t border-border pt-5">
        <h3 class="font-black text-brand-navy-900">Existing versions</h3>
        <p class="mt-1 text-xs text-muted">Versions are immutable. To change the wording, use <strong>Revise</strong> — it pre-fills the form above to save the next version.</p>
        <div class="mt-3 space-y-2">
            @forelse($templates as $template)
                @php $usage = ($templateUsage[$template->id] ?? 0); @endphp
                <div class="rounded-lg border border-border bg-slate-50 p-3 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-bold text-brand-navy-900">{{ $template->code }} v{{ $template->version }}</p>
                            <p class="text-xs text-muted">{{ str($template->purpose)->headline() }} · {{ $usage === 0 ? 'Not yet used' : 'Used by '.$usage.' message'.($usage === 1 ? '' : 's') }}</p>
                        </div>
                        <x-ui.status-badge :status="$template->is_active ? 'active' : 'inactive'" />
                    </div>
                    <p class="mt-2 break-words rounded bg-white px-2 py-1 font-mono text-xs text-slate-600">{{ $template->content }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
                        <form method="POST" action="{{ route('communications.templates.status', $template) }}" data-ajax>
                            @csrf @method('PATCH')
                            <input type="hidden" name="is_active" value="{{ $template->is_active ? 0 : 1 }}">
                            <button type="submit" class="text-xs font-bold {{ $template->is_active ? 'text-warning-800' : 'text-success-700' }} hover:underline">{{ $template->is_active ? 'Deactivate' : 'Activate' }}</button>
                        </form>

                        <button type="button" data-revise-template
                            data-code="{{ $template->code }}"
                            data-purpose="{{ $template->purpose }}"
                            data-content="{{ $template->content }}"
                            class="text-xs font-bold text-brand-navy-700 hover:underline">Revise (new version)</button>

                        @if ($usage === 0)
                            <details class="inline-block">
                                <summary class="cursor-pointer list-none text-xs font-bold text-brand-red-600 hover:underline">Delete</summary>
                                <form method="POST" action="{{ route('communications.templates.destroy', $template) }}" data-ajax class="mt-2 w-64 rounded-lg border border-border bg-white p-3 shadow-lg">
                                    @csrf @method('DELETE')
                                    <p class="text-xs font-semibold text-brand-navy-900">Delete {{ $template->code }} v{{ $template->version }}?</p>
                                    <p class="mt-1 text-xs text-muted">It has never been used, so it can be safely removed. This cannot be undone.</p>
                                    <button type="submit" class="jca-button-danger mt-2 w-full">Confirm delete</button>
                                </form>
                            </details>
                        @else
                            <span class="text-xs text-muted">Delete unavailable (in use)</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-muted">No SMS templates have been created.</p>
            @endforelse
        </div>
    </div>
</section>
