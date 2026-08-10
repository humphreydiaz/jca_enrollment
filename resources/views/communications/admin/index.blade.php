<x-layouts.app title="Communications">
    <div class="mx-auto max-w-6xl space-y-6">
        <header>
            <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-brand-orange-500">Admin workspace</p>
            <h1 class="mt-2 text-3xl font-black text-brand-navy-900">Communications</h1>
            <p class="mt-2 max-w-3xl text-muted">Publish safe, audience-targeted announcements and manage SMS template versions. Live SMS remains disabled until JCA approves its production sender, consent wording, and templates.</p>
        </header>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="jca-card p-5 sm:p-6" aria-labelledby="announcement-form-title">
                <h2 id="announcement-form-title" class="text-xl font-black text-brand-navy-900">New announcement</h2>
                <p class="mt-1 text-sm text-muted">Portal announcements may be saved as drafts or published immediately.</p>

                <form class="mt-5 space-y-4" method="POST" action="{{ route('communications.store') }}" x-data="{ audience: @js(old('audiences.0.type', 'all')) }">
                    @csrf

                    <x-ui.field for="announcement_title" label="Title">
                        <input id="announcement_title" name="title" type="text" maxlength="160" required value="{{ old('title') }}" class="jca-input" autocomplete="off">
                    </x-ui.field>

                    <x-ui.field for="announcement_body" label="Message" hint="Keep school-wide messages general. Do not include support, document, or financial details.">
                        <textarea id="announcement_body" name="body" class="jca-input min-h-32" rows="5" maxlength="1000" required>{{ old('body') }}</textarea>
                    </x-ui.field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field for="announcement_status" label="Publication">
                            <select id="announcement_status" name="status" class="jca-input" required>
                                <option value="draft" @selected(old('status', 'draft') === 'draft')>Save draft</option>
                                <option value="published" @selected(old('status') === 'published')>Publish now</option>
                            </select>
                        </x-ui.field>

                        <x-ui.field for="sms_template_public_id" label="Optional SMS template" hint="Only consented guardians in the selected audience are queued.">
                            <select id="sms_template_public_id" name="sms_template_public_id" class="jca-input">
                                <option value="">Do not send SMS</option>
                                @foreach($templates->where('purpose', 'announcement')->where('is_active', true) as $template)
                                    <option value="{{ $template->public_id }}" @selected(old('sms_template_public_id') === $template->public_id)>{{ $template->code }} v{{ $template->version }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    </div>

                    <fieldset class="rounded-xl border border-border p-4">
                        <legend class="px-1 text-sm font-bold text-brand-navy-900">Audience</legend>
                        <div class="mt-1 flex flex-col gap-3 sm:flex-row sm:gap-6">
                            <label class="flex items-center gap-2 text-sm font-semibold">
                                <input type="radio" name="audiences[0][type]" value="all" x-model="audience">
                                All authenticated school portals
                            </label>
                            <label class="flex items-center gap-2 text-sm font-semibold">
                                <input type="radio" name="audiences[0][type]" value="role" x-model="audience">
                                One portal role
                            </label>
                        </div>
                        <div class="mt-4" x-show="audience === 'role'">
                            <label class="jca-label" for="audience_role">Portal role</label>
                            <select id="audience_role" class="jca-input mt-1" name="audiences[0][role_code]">
                                @foreach(['guardian' => 'Guardians', 'student' => 'Students', 'teacher' => 'Teachers', 'registrar' => 'Registrars', 'accounting' => 'Accounting'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('audiences.0.role_code', 'guardian') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </fieldset>

                    <x-ui.button type="submit">Save announcement</x-ui.button>
                </form>
            </section>

            <section class="jca-card p-5 sm:p-6" aria-labelledby="template-title">
                <h2 id="template-title" class="text-xl font-black text-brand-navy-900">Create SMS template version</h2>
                <p class="mt-1 text-sm text-muted">Saving creates a new immutable version. Activating it deactivates older versions with the same code.</p>

                <form class="mt-5 space-y-4" method="POST" action="{{ route('communications.templates.store') }}">
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
                    <div class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
                        @forelse($templates as $template)
                            <div class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 p-3 text-sm">
                                <div><p class="font-bold text-brand-navy-900">{{ $template->code }} v{{ $template->version }}</p><p class="text-xs text-muted">{{ str($template->purpose)->headline() }}</p></div>
                                <x-ui.status-badge :status="$template->is_active ? 'active' : 'inactive'" />
                            </div>
                        @empty
                            <p class="text-sm text-muted">No SMS templates have been created.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>

        <section class="jca-card p-5 sm:p-6" aria-labelledby="failed-sms-title">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 id="failed-sms-title" class="text-xl font-black text-brand-navy-900">Failed SMS deliveries</h2><p class="mt-1 text-sm text-muted">Retrying queues the existing notification; it does not create a duplicate notification.</p></div>
                <x-ui.status-badge :status="$failedNotifications->isEmpty() ? 'clear' : 'failed'" :label="$failedNotifications->isEmpty() ? 'Queue clear' : $failedNotifications->count().' failed'" />
            </div>
            <div class="mt-4 space-y-3">
                @forelse($failedNotifications as $notification)
                    <article class="flex flex-col justify-between gap-4 rounded-xl border border-border p-4 sm:flex-row sm:items-center">
                        <div><p class="font-bold text-brand-navy-900">{{ str($notification->purpose)->headline() }}</p><p class="mt-1 text-sm text-muted">Attempted {{ $notification->message?->attempt_count ?? 0 }} time(s); failure code: {{ $notification->message?->failure_code ?? 'unavailable' }}</p></div>
                        <form method="POST" action="{{ route('communications.notifications.retry', $notification) }}">@csrf<x-ui.button variant="secondary" type="submit">Retry safely</x-ui.button></form>
                    </article>
                @empty
                    <x-ui.empty-state title="No failed SMS deliveries" description="Failed queued messages will appear here with a safe retry action." icon="communications" />
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
