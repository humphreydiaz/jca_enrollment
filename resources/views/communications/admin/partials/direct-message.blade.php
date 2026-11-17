{{-- D-052: text one or more people picked by name. Sent whether or not they opted in to announcement texts. --}}
<section data-section="direct" class="jca-card" aria-labelledby="direct-title"
    x-data="commsDirect(@js(route('communications.people')), @js($creditsPerPart))">
    <div class="border-b border-border px-5 py-4 sm:px-6">
        <h2 id="direct-title" class="flex items-center gap-2 text-xl font-black text-brand-navy-900"><x-ui.icon name="message" /> Message people</h2>
        <p class="mt-1 text-sm text-muted">Tick one or more people with a number on file. These texts always send, even to people who have not opted in.</p>
    </div>

    <form x-ref="form" method="POST" action="{{ route('communications.direct') }}" data-ajax class="space-y-4 p-5 sm:p-6" @reset="reset()">
        @csrf
        <template x-for="person in selected" :key="person.key"><input type="hidden" name="recipients[]" :value="person.key"></template>

        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_10rem]">
            <label class="sr-only" for="people_search">Search people</label>
            <input id="people_search" type="search" x-model="search" @input="queue()" @keydown.enter.prevent="load()" class="jca-input mt-0" placeholder="Name, Student ID, Staff ID, or number" autocomplete="off">
            <label class="sr-only" for="people_group">Group</label>
            <select id="people_group" x-model="group" @change="load()" class="jca-input mt-0">
                <option value="">All groups</option>
                @foreach ($roles as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="overflow-hidden rounded-xl border border-border">
            <div class="flex items-center justify-between gap-3 border-b border-border bg-slate-50 px-3 py-2 text-xs font-bold text-muted">
                <label class="inline-flex items-center gap-2" x-show="results.length > 0">
                    <input type="checkbox" :checked="allShownSelected" @change="toggleShown()"> Tick all shown
                </label>
                <span x-show="results.length === 0"></span>
                <span x-text="loading ? 'Searching…' : results.length + (results.length === 1 ? ' person' : ' people') + ' with a number'"></span>
            </div>
            <ul class="max-h-72 divide-y divide-border overflow-y-auto" aria-label="People">
                <template x-for="person in results" :key="person.key">
                    <li>
                        <label class="flex cursor-pointer items-center gap-3 px-3 py-2.5 hover:bg-slate-50" :class="isSelected(person.key) && 'bg-brand-blue-100/50'">
                            <input type="checkbox" :checked="isSelected(person.key)" @change="toggle(person)">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold text-brand-navy-900" x-text="person.name"></span>
                                <span class="block truncate text-xs text-muted" x-text="person.detail"></span>
                            </span>
                            <span class="shrink-0 text-xs font-semibold tabular-nums text-muted" x-text="person.mobile"></span>
                        </label>
                    </li>
                </template>
                <li x-show="searched && ! loading && results.length === 0" class="px-3 py-6 text-center text-sm text-muted">No one with a mobile number matches.</li>
            </ul>
        </div>

        <div x-show="selected.length > 0" x-cloak>
            <p class="text-sm font-bold text-ink"><span x-text="selected.length"></span> selected</p>
            <div class="mt-2 flex flex-wrap gap-1.5">
                <template x-for="person in selected" :key="person.key">
                    <span class="inline-flex items-center gap-1 rounded-full bg-brand-blue-100 py-1 pl-3 pr-1 text-xs font-bold text-brand-navy-900">
                        <span x-text="person.name"></span>
                        <button type="button" class="grid size-5 place-items-center rounded-full hover:bg-white" @click="toggle(person)" :aria-label="'Remove ' + person.name">&times;</button>
                    </span>
                </template>
                <button type="button" class="text-xs font-bold text-brand-red-600 underline" @click="selected = []">Clear</button>
            </div>
        </div>

        <div>
            <div class="flex items-end justify-between gap-3">
                <label for="direct_body" class="text-sm font-bold text-ink">Text message</label>
                <span class="text-xs font-semibold tabular-nums" :class="sms.parts > 3 ? 'text-brand-red-600' : 'text-muted'"
                    x-text="sms.length + ' / ' + sms.limit + ' characters · ' + sms.parts + (sms.parts === 1 ? ' SMS' : ' SMS parts')"></span>
            </div>
            <textarea id="direct_body" name="body" x-model="body" rows="4" maxlength="480" class="jca-input mt-2" required placeholder="e.g. Good day! Please visit the Registrar's office tomorrow."></textarea>
            <p class="mt-1 text-xs text-brand-red-600" x-show="sms.parts > 3">Too long: keep it to 3 SMS parts.</p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-5">
            <p class="text-xs text-muted" x-show="selected.length > 0">About <strong class="text-ink" x-text="credits"></strong> credits</p>
            <button type="button" class="jca-button-primary ml-auto" @click="send()">
                <x-ui.icon name="message" size="size-4" /> <span x-text="selected.length > 1 ? 'Send to ' + selected.length + ' people' : 'Send text'">Send text</span>
            </button>
        </div>

        <dialog x-ref="confirm" class="m-auto w-[min(28rem,calc(100%-2rem))] rounded-2xl border-0 bg-white p-0 text-ink shadow-2xl backdrop:bg-slate-950/55">
            <div class="border-b border-border p-5">
                <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-brand-orange-500">Send text message?</p>
                <p class="mt-1 text-lg font-black text-brand-navy-900">To <span x-text="selected.length"></span> <span x-text="selected.length === 1 ? 'person' : 'people'"></span></p>
            </div>
            <div class="space-y-3 p-5 text-sm">
                <p class="text-muted" x-text="selected.slice(0, 6).map((person) => person.name).join(', ') + (selected.length > 6 ? ' and ' + (selected.length - 6) + ' more' : '')"></p>
                <p class="whitespace-pre-line rounded-xl bg-slate-50 p-3" x-text="body"></p>
                <p class="text-muted">About <strong class="text-ink" x-text="credits"></strong> credits. A number shared by several people gets one text.</p>
            </div>
            <div class="flex justify-end gap-3 border-t border-border p-5">
                <button type="button" class="jca-button-secondary" @click="$refs.confirm.close()">Cancel</button>
                <button type="button" class="jca-button-primary" @click="confirmSend()">Send now</button>
            </div>
        </dialog>
    </form>
</section>
