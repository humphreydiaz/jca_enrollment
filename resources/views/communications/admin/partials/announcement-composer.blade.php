<section data-section="announcement-composer" class="jca-card p-5 sm:p-6" aria-labelledby="announcement-form-title">
    <h2 id="announcement-form-title" class="text-xl font-black text-brand-navy-900">New announcement</h2>
    <p class="mt-1 text-sm text-muted">Portal announcements may be saved as drafts or published immediately.</p>

    <form class="audience-toggle mt-5 space-y-4" method="POST" action="{{ route('communications.store') }}" data-ajax>
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
                    <input type="radio" name="audiences[0][type]" value="all" @checked(old('audiences.0.type', 'all') === 'all')>
                    All authenticated school portals
                </label>
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="radio" name="audiences[0][type]" value="role" @checked(old('audiences.0.type') === 'role')>
                    One portal role
                </label>
            </div>
            <div class="audience-role-field mt-4">
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
