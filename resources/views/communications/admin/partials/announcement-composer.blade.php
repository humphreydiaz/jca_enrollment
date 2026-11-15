{{-- D-052: one announcement — portal, SMS, or both — to Everyone or chosen groups. --}}
<section data-section="composer" class="jca-card" aria-labelledby="composer-title"
    x-data="commsComposer(@js(route('communications.audience')), @js($creditsPerPart))">
    <div class="border-b border-border px-5 py-4 sm:px-6">
        <h2 id="composer-title" class="flex items-center gap-2 text-xl font-black text-brand-navy-900"><x-ui.icon name="announcement" /> New announcement</h2>
        <p class="mt-1 text-sm text-muted">For a group of people. Texts go only to people who opted in.</p>
    </div>

    <form x-ref="form" method="POST" action="{{ route('communications.store') }}" data-ajax class="space-y-5 p-5 sm:p-6" @reset="reset()">
        @csrf
        <input type="hidden" name="status" :value="status">

        <fieldset>
            <legend class="text-sm font-bold text-ink">Send as</legend>
            <div class="mt-2 grid grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1" role="radiogroup">
                @foreach ($deliveries as $value => $label)
                    <label class="cursor-pointer">
                        <input type="radio" name="delivery" value="{{ $value }}" x-model="delivery" class="peer sr-only" @checked($value === 'portal')>
                        <span class="block rounded-lg px-2 py-2 text-center text-sm font-bold text-brand-navy-700 transition peer-checked:bg-white peer-checked:text-brand-navy-900 peer-checked:shadow-sm peer-focus-visible:ring-2 peer-focus-visible:ring-brand-navy-700">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <x-ui.field for="announcement_title" label="Title">
            <input id="announcement_title" name="title" x-model="title" @input="suggestSms()" maxlength="160" required class="jca-input mt-0" placeholder="e.g. No classes on Friday">
        </x-ui.field>

        <div x-show="showsPortal">
            <x-ui.field for="announcement_body" label="Message on the portal">
                <textarea id="announcement_body" name="body" x-model="body" @input="suggestSms()" rows="5" maxlength="2000" class="jca-input mt-0" :required="showsPortal" :disabled="! showsPortal"></textarea>
            </x-ui.field>
        </div>

        <div x-show="sendsSms" x-cloak>
            <div class="flex items-end justify-between gap-3">
                <label for="announcement_sms" class="text-sm font-bold text-ink">Text message</label>
                <span class="text-xs font-semibold tabular-nums" :class="sms.parts > 3 ? 'text-brand-red-600' : 'text-muted'"
                    x-text="sms.length + ' / ' + sms.limit + ' characters · ' + sms.parts + (sms.parts === 1 ? ' SMS' : ' SMS parts') + (sms.plain ? '' : ' · special characters')"></span>
            </div>
            <textarea id="announcement_sms" name="sms_body" x-model="smsBody" @input="smsEdited = true" rows="4" maxlength="480" class="jca-input mt-2" :required="sendsSms" :disabled="! sendsSms"
                placeholder="Short version for phones"></textarea>
            <p class="mt-1 text-xs text-muted" x-show="showsPortal">Filled in from the title and message; edit it to keep it short. One SMS is 160 plain characters.</p>
            <p class="mt-1 text-xs text-brand-red-600" x-show="sms.parts > 3">Too long: keep it to 3 SMS parts.</p>
        </div>

        <fieldset>
            <legend class="text-sm font-bold text-ink">Who receives it</legend>
            <label class="mt-2 flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-bold text-brand-navy-900">
                <input type="checkbox" name="everyone" value="1" x-model="everyone" @change="toggleEveryone()"> Everyone
            </label>
            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3" :class="everyone && 'opacity-50'">
                @foreach ($roles as $code => $label)
                    <label class="flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm">
                        <input type="checkbox" name="audience_roles[]" value="{{ $code }}" x-model="roles" :disabled="everyone"> {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="flex flex-wrap gap-3 border-t border-border pt-5">
            <button type="button" class="jca-button-primary" @click="submit('published')" :disabled="loading">
                <x-ui.icon name="announcement" size="size-4" /> <span x-text="loading ? 'Counting recipients…' : publishLabel">Publish</span>
            </button>
            <button type="button" class="jca-button-secondary" @click="submit('draft')">Save draft</button>
        </div>

        {{-- Confirm before any text is sent: who gets it and what it costs. --}}
        <dialog x-ref="confirm" class="m-auto w-[min(30rem,calc(100%-2rem))] rounded-2xl border-0 bg-white p-0 text-ink shadow-2xl backdrop:bg-slate-950/55">
            <div class="border-b border-border p-5">
                <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-brand-orange-500">Send text message?</p>
                <h3 class="mt-1 text-lg font-black text-brand-navy-900" x-text="title || 'Announcement'"></h3>
            </div>
            <div class="space-y-3 p-5 text-sm">
                <p x-show="previewError" class="rounded-lg bg-red-50 p-3 font-semibold text-brand-red-600" x-text="previewError"></p>
                <template x-if="preview">
                    <div class="space-y-3">
                        <p class="text-base"><strong class="text-2xl font-black text-brand-navy-900" x-text="preview.total"></strong> <span x-text="preview.total === 1 ? 'person will get this text' : 'people will get this text'"></span></p>
                        <ul class="divide-y divide-border rounded-xl border border-border">
                            <template x-for="group in preview.groups" :key="group.label">
                                <li class="flex items-center justify-between gap-3 px-3 py-2">
                                    <span class="font-bold" x-text="group.label"></span>
                                    <span class="text-right text-xs text-muted"><span class="font-bold text-ink" x-text="group.opted_in"></span> opted in · <span x-text="group.with_number - group.opted_in"></span> not opted in · <span x-text="group.members - group.with_number"></span> no number</span>
                                </li>
                            </template>
                        </ul>
                        <p class="text-muted">About <strong class="text-ink" x-text="preview.credits"></strong> credits (<span x-text="preview.parts"></span> SMS <span x-text="preview.parts === 1 ? 'part' : 'parts'"></span> × <span x-text="preview.credits_per_part"></span> credits each). A number shared by several people gets one text.</p>
                        <p x-show="preview.total === 0" class="rounded-lg bg-warning-100 p-3 text-warning-800">No one in this audience can receive texts yet. <span x-show="showsPortal">The portal announcement is still published.</span></p>
                    </div>
                </template>
            </div>
            <div class="flex justify-end gap-3 border-t border-border p-5">
                <button type="button" class="jca-button-secondary" @click="$refs.confirm.close()">Cancel</button>
                <button type="button" class="jca-button-primary" @click="confirmSend()" x-text="publishLabel"></button>
            </div>
        </dialog>
    </form>
</section>
