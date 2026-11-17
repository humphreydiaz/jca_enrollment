import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('userManagement', (users = []) => ({
    selectedUser: null,
    selectedRoles: [],
    reason: '',

    openAccount(publicId, rolesOverride = null, reason = '') {
        this.selectedUser = users.find((user) => user.publicId === publicId) ?? null;

        if (!this.selectedUser) {
            return;
        }

        this.selectedRoles = rolesOverride ?? [...this.selectedUser.roles];
        this.reason = reason ?? '';

        this.$nextTick(() => {
            if (!this.$refs.accountDialog.open) {
                this.$refs.accountDialog.showModal();
            }
        });
    },

    closeAccount() {
        if (this.$refs.accountDialog.open) {
            this.$refs.accountDialog.close();
        }
    },
}));

Alpine.data('enrollmentReviewWorkspace', (rows = [], restoredRow = null, restoredRequirementId = null) => ({
    openRows: rows.some((row) => row.publicId === restoredRow) ? [restoredRow] : [],
    selected: [],
    previewName: '',
    previewUrl: '',

    init() {
        if (!this.openRows.length) {
            return;
        }

        this.$nextTick(() => {
            const target = document.getElementById(`requirement-card-${restoredRequirementId}`)
                ?? document.getElementById(`enrollment-review-${restoredRow}`);
            target?.scrollIntoView({ block: 'center' });
        });
    },

    // Expandable detail rows
    toggleRow(publicId) {
        const idx = this.openRows.indexOf(publicId);
        if (idx === -1) {
            this.openRows.push(publicId);
        } else {
            this.openRows.splice(idx, 1);
        }
    },

    isRowOpen(publicId) {
        return this.openRows.includes(publicId);
    },

    // Bulk selection
    toggle(publicId) {
        const idx = this.selected.indexOf(publicId);
        if (idx === -1) {
            this.selected.push(publicId);
        } else {
            this.selected.splice(idx, 1);
        }
    },

    isSelected(publicId) {
        return this.selected.includes(publicId);
    },

    toggleAll() {
        if (this.allSelected) {
            this.selected = [];
        } else {
            this.selected = rows.map((r) => r.publicId);
        }
    },

    get allSelected() {
        return rows.length > 0 && this.selected.length === rows.length;
    },

    get someSelected() {
        return this.selected.length > 0 && this.selected.length < rows.length;
    },

    get hasSelection() {
        return this.selected.length > 0;
    },

    confirmBulk(event) {
        if (!window.confirm(`Apply this status change to ${this.selected.length} selected application(s)? Applications that do not permit this transition will be skipped.`)) {
            event.preventDefault();
        }
    },
}));

Alpine.data('sectionAssignmentWorkspace', (placements = [], assignments = [], promotions = [], restored = {}) => ({
    selectedPlacement: null,
    selectedAssignment: null,
    selectedPromotion: null,
    placementSection: '',
    transferSection: '',
    transferReason: '',
    targetYear: '',
    supportConfirmed: false,
    capacityOverride: false,
    overrideReason: '',
    activeTab: 'awaiting-placement',
    selectedPromotions: [],

    init() {
        const hash = window.location.hash?.replace('#', '');
        if (['awaiting-placement', 'current-assignments', 'transfer-history', 'promotion-review'].includes(hash)) {
            this.activeTab = hash;
        }

        const justReviewed = this.$root.querySelector('[data-just-reviewed]');
        if (justReviewed && !(restored?.context && restored?.publicId)) {
            this.activeTab = 'promotion-review';
            this.$nextTick(() => justReviewed.scrollIntoView({ block: 'center' }));
        }

        if (restored?.context && restored?.publicId) {
            this.$nextTick(() => {
                if (restored.context === 'placement') {
                    this.openPlacement(restored.publicId, restored);
                } else if (restored.context === 'transfer') {
                    this.openTransfer(restored.publicId, restored);
                } else if (restored.context === 'promotion') {
                    this.activeTab = 'promotion-review';
                    this.openPromotion(restored.publicId, restored);
                }
            });
        }
    },

    switchTab(tab) {
        this.activeTab = tab;
        window.history.replaceState(null, '', '#' + tab);
    },

    resetActionState() {
        this.supportConfirmed = false;
        this.capacityOverride = false;
        this.overrideReason = '';
    },

    // Promotion checkbox helpers
    togglePromotion(publicId) {
        const idx = this.selectedPromotions.indexOf(publicId);
        if (idx === -1) {
            this.selectedPromotions.push(publicId);
        } else {
            this.selectedPromotions.splice(idx, 1);
        }
    },

    isPromotionSelected(publicId) {
        return this.selectedPromotions.includes(publicId);
    },

    toggleAllPromotions() {
        if (this.allPromotionsSelected) {
            this.selectedPromotions = [];
        } else {
            this.selectedPromotions = promotions.map((p) => p.publicId);
        }
    },

    selectAllEligiblePromotions() {
        this.selectedPromotions = promotions
            .filter((p) => p.createAction)
            .map((p) => p.publicId);
    },

    get eligiblePromotionCount() {
        return promotions.filter((p) => p.createAction).length;
    },

    confirmMassApprove(event) {
        if (!window.confirm(`Approve and create next-year drafts for ${this.selectedPromotions.length} selected student(s)? Students that are not eligible or already have a draft will be skipped.`)) {
            event.preventDefault();
        }
    },

    get allPromotionsSelected() {
        return promotions.length > 0 && this.selectedPromotions.length === promotions.length;
    },

    get somePromotionsSelected() {
        return this.selectedPromotions.length > 0 && this.selectedPromotions.length < promotions.length;
    },

    get hasPromotionSelection() {
        return this.selectedPromotions.length > 0;
    },

    openPlacement(publicId, values = {}) {
        this.selectedPlacement = placements.find((record) => record.publicId === publicId) ?? null;
        if (!this.selectedPlacement) return;
        this.resetActionState();
        this.placementSection = values.section ?? this.selectedPlacement.recommendedSection ?? '';
        this.supportConfirmed = Boolean(values.supportConfirmed);
        this.capacityOverride = Boolean(values.capacityOverride);
        this.overrideReason = values.overrideReason ?? '';
        if (!this.$refs.placementDialog.open) this.$refs.placementDialog.showModal();
    },

    openTransfer(publicId, values = {}) {
        this.selectedAssignment = assignments.find((record) => record.publicId === publicId) ?? null;
        if (!this.selectedAssignment) return;
        this.resetActionState();
        this.transferSection = values.section ?? '';
        this.transferReason = values.reason ?? '';
        this.supportConfirmed = Boolean(values.supportConfirmed);
        this.capacityOverride = Boolean(values.capacityOverride);
        this.overrideReason = values.overrideReason ?? '';
        if (!this.$refs.transferDialog.open) this.$refs.transferDialog.showModal();
    },

    openPromotion(publicId, values = {}) {
        this.selectedPromotion = promotions.find((record) => record.publicId === publicId) ?? null;
        if (!this.selectedPromotion) return;
        this.targetYear = values.targetYear ?? '';
        if (!this.$refs.promotionDialog.open) this.$refs.promotionDialog.showModal();
    },

    closePlacement() {
        if (this.$refs.placementDialog.open) this.$refs.placementDialog.close();
        this.selectedPlacement = null;
    },

    closeTransfer() {
        if (this.$refs.transferDialog.open) this.$refs.transferDialog.close();
        this.selectedAssignment = null;
    },

    closePromotion() {
        if (this.$refs.promotionDialog.open) this.$refs.promotionDialog.close();
        this.selectedPromotion = null;
    },
}));

// Registrar student directory: the learner record is fetched on demand rather
// than rendered into every table row, so a page of results never carries the
// household and demographic detail of 25 learners in its HTML.
Alpine.data('studentDirectory', () => ({
    recordName: '',
    recordUrl: '',
    recordHtml: '',
    loading: false,
    error: '',

    async open(url, name) {
        this.recordName = name;
        this.recordUrl = url;
        this.recordHtml = '';
        this.error = '';
        this.loading = true;

        if (!this.$refs.recordDialog.open) {
            this.$refs.recordDialog.showModal();
        }

        try {
            const response = await fetch(url, {
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error('load failed');
            }
            this.recordHtml = await response.text();
        } catch (error) {
            this.error = 'This record could not be loaded. Use "Open full record" to read it directly.';
        } finally {
            this.loading = false;
        }
    },

    reset() {
        this.recordHtml = '';
        this.error = '';
        this.loading = false;
    },
}));

// Teacher "My teaching": one section at a time, student summary in a dialog.
// The summary is fetched (and access-logged server-side) only when a student is opened.
Alpine.data('teacherWorkspace', (firstSection = null) => ({
    activeSection: firstSection,
    studentName: '',
    studentHtml: '',
    studentLoading: false,
    studentError: '',
    studentRequest: 0,

    init() {
        const hash = window.location.hash?.replace('#section-', '');
        if (hash && this.$root.querySelector(`#section-${CSS.escape(hash)}`)) {
            this.activeSection = hash;
        }
    },

    // Called from a link's click. If this script is missing, the link simply opens the full page.
    async openStudent(event, name) {
        const url = event.currentTarget.href;
        event.preventDefault();
        const request = ++this.studentRequest;
        this.studentName = name;
        this.studentHtml = '';
        this.studentError = '';
        this.studentLoading = true;
        if (!this.$refs.studentDialog.open) this.$refs.studentDialog.showModal();

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                credentials: 'same-origin',
            });
            if (request !== this.studentRequest) return;
            if (response.redirected) {
                this.studentError = 'Your session has ended. Refresh the page and sign in again.';
                return;
            }
            if (!response.ok) {
                this.studentError = response.status === 404
                    ? 'This student is no longer in one of your current sections.'
                    : 'The summary could not be loaded. Please try again.';
                return;
            }
            this.studentHtml = await response.text();
        } catch {
            if (request === this.studentRequest) this.studentError = 'The summary could not be loaded. Check your connection and try again.';
        } finally {
            if (request === this.studentRequest) this.studentLoading = false;
        }
    },

    closeStudent() {
        this.studentRequest++;
        this.studentHtml = '';
        this.studentLoading = false;
    },
}));

// Registrar "Grade approval": class review dialog and approving several reviewed classes.
// A class becomes selectable only after it has been opened; the server enforces the
// same rule (and approves only the grades that were shown, unchanged).
Alpine.data('gradeApproval', (reviewed = [], preselect = []) => ({
    reviewed: [...reviewed],
    selected: preselect.filter((key) => reviewed.includes(key)),
    classTitle: '',
    classHtml: '',
    classLoading: false,
    classError: '',
    classRequest: 0,

    get anyReviewed() {
        return this.reviewedOnPage().length > 0;
    },

    reviewedOnPage() {
        return [...this.$root.querySelectorAll('input[name="classes[]"][type="checkbox"]')]
            .map((box) => box.value)
            .filter((key) => this.reviewed.includes(key));
    },

    isReviewed(key) {
        return this.reviewed.includes(key);
    },

    toggleAllReviewed(checked) {
        this.selected = checked ? this.reviewedOnPage() : [];
    },

    confirmBulk(event) {
        if (this.selected.length === 0) {
            event.preventDefault();
            return;
        }
        if (!window.confirm(`Approve the reviewed grades in ${this.selected.length} class(es)? Any grade changed since you opened its class will be skipped.`)) {
            event.preventDefault();
        }
    },

    // Called from a link's click. If this script is missing, the link simply opens the full page.
    async openClass(event, key, title) {
        const url = event.currentTarget.href;
        event.preventDefault();
        const request = ++this.classRequest;
        this.classTitle = title;
        this.classHtml = '';
        this.classError = '';
        this.classLoading = true;
        if (!this.$refs.classDialog.open) this.$refs.classDialog.showModal();

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                credentials: 'same-origin',
            });
            if (request !== this.classRequest) return;
            if (response.redirected) {
                this.classError = 'Your session has ended. Refresh the page and sign in again.';
                return;
            }
            if (!response.ok) {
                this.classError = response.status === 404
                    ? 'This class is no longer available for grading.'
                    : 'The class could not be loaded. Please try again.';
                return;
            }
            this.classHtml = await response.text();
            if (!this.reviewed.includes(key)) this.reviewed.push(key);
        } catch {
            if (request === this.classRequest) this.classError = 'The class could not be loaded. Check your connection and try again.';
        } finally {
            if (request === this.classRequest) this.classLoading = false;
        }
    },

    closeClass() {
        this.classRequest++;
        this.classHtml = '';
        this.classLoading = false;
    },
}));


// ── Communications (D-052) ────────────────────────────────────────────────────
// Mirrors App\Modules\Communications\Support\SmsLength: plain text is 160 per SMS (153 per part
// when longer); any other character makes it Unicode, 70 (67 per part).
const SMS_GSM = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà^{}\\[~]|€";
const smsInfo = (text) => {
    const characters = [...(text || '')];
    const plain = characters.every((character) => SMS_GSM.includes(character));
    const [single, multi] = plain ? [160, 153] : [70, 67];
    const length = characters.length;
    const parts = length === 0 ? 0 : (length <= single ? 1 : Math.ceil(length / multi));
    const limit = parts <= 1 ? single : parts * multi;
    return { length, parts, plain, limit };
};

const commsToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

Alpine.data('commsComposer', (audienceUrl, creditsPerPart = 2) => ({
    delivery: 'portal',
    everyone: false,
    roles: [],
    title: '',
    body: '',
    smsBody: '',
    smsEdited: false,
    status: 'published',
    preview: null,
    previewError: '',
    loading: false,

    get sendsSms() { return this.delivery !== 'portal'; },
    get showsPortal() { return this.delivery !== 'sms'; },
    get sms() { return smsInfo(this.smsBody); },
    get publishLabel() {
        return this.delivery === 'portal' ? 'Publish' : (this.delivery === 'sms' ? 'Send SMS' : 'Publish & send SMS');
    },

    suggestSms() {
        if (this.smsEdited) {
            return;
        }
        const text = [this.title.trim(), this.body.trim()].filter(Boolean).join(': ');
        this.smsBody = text.length > 300 ? text.slice(0, 297).trimEnd() + '...' : text;
    },

    toggleEveryone() {
        if (this.everyone) {
            this.roles = [];
        }
    },

    reset() {
        Object.assign(this, { delivery: 'portal', everyone: false, roles: [], title: '', body: '', smsBody: '', smsEdited: false, status: 'published', preview: null, previewError: '' });
    },

    async submit(status) {
        this.status = status;
        await this.$nextTick();
        if (status === 'draft' || !this.sendsSms) {
            this.$refs.form.requestSubmit();
            return;
        }
        this.loading = true;
        this.previewError = '';
        try {
            const data = new FormData();
            data.append('everyone', this.everyone ? '1' : '0');
            this.roles.forEach((role) => data.append('audience_roles[]', role));
            data.append('sms_body', this.smsBody);
            const response = await fetch(audienceUrl, { method: 'POST', body: data, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': commsToken() } });
            if (!response.ok) {
                throw new Error('preview failed');
            }
            this.preview = await response.json();
        } catch (error) {
            this.preview = null;
            this.previewError = 'Could not count the recipients. Check your connection and try again.';
        } finally {
            this.loading = false;
        }
        this.$refs.confirm.showModal();
    },

    confirmSend() {
        this.$refs.confirm.close();
        this.$refs.form.requestSubmit();
    },
}));

Alpine.data('commsDirect', (peopleUrl, creditsPerPart = 2) => ({
    search: '',
    group: '',
    results: [],
    selected: [],
    body: '',
    loading: false,
    searched: false,
    timer: null,
    request: 0,

    get sms() { return smsInfo(this.body); },
    get credits() { return this.sms.parts * creditsPerPart * this.selected.length; },

    init() {
        this.load();
    },

    queue() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.load(), 250);
    },

    async load() {
        const ticket = ++this.request;
        this.loading = true;
        try {
            const url = new URL(peopleUrl, window.location.origin);
            url.searchParams.set('search', this.search);
            if (this.group) {
                url.searchParams.set('group', this.group);
            }
            const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            if (ticket === this.request) {
                this.results = data.people || [];
                this.searched = true;
            }
        } catch (error) {
            if (ticket === this.request) {
                this.results = [];
            }
        } finally {
            if (ticket === this.request) {
                this.loading = false;
            }
        }
    },

    isSelected(key) { return this.selected.some((person) => person.key === key); },

    toggle(person) {
        this.isSelected(person.key)
            ? this.selected = this.selected.filter((picked) => picked.key !== person.key)
            : this.selected.push(person);
    },

    get allShownSelected() {
        return this.results.length > 0 && this.results.every((person) => this.isSelected(person.key));
    },

    toggleShown() {
        if (this.allShownSelected) {
            const shown = this.results.map((person) => person.key);
            this.selected = this.selected.filter((person) => !shown.includes(person.key));
        } else {
            this.results.forEach((person) => { if (!this.isSelected(person.key)) this.selected.push(person); });
        }
    },

    reset() {
        this.selected = [];
        this.body = '';
    },

    send() {
        if (this.selected.length === 0 || this.body.trim() === '') {
            this.$refs.form.requestSubmit();
            return;
        }
        this.$refs.confirm.showModal();
    },

    confirmSend() {
        this.$refs.confirm.close();
        this.$refs.form.requestSubmit();
    },
}));

Alpine.data('commsHistory', (tab = 'announcements') => ({
    tab,
    draft: null,
    draftLoading: false,
    draftError: '',

    async publish(url, audienceUrl, title) {
        this.draft = { url, title, info: null };
        this.draftError = '';
        this.draftLoading = true;
        this.$refs.publish.showModal();
        try {
            const response = await fetch(audienceUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) {
                throw new Error('preview failed');
            }
            this.draft.info = await response.json();
        } catch (error) {
            this.draftError = 'Could not count the recipients. You can still publish.';
        } finally {
            this.draftLoading = false;
        }
    },
}));

Alpine.start();

// Preserve scroll position across full-page form posts on opted-in pages
// (any page with a [data-preserve-scroll] root). Long configuration screens
// otherwise bounce the admin to the top on every save; here we restore where
// they were. Skipped when validation errors surfaced — those render at the top
// and must be seen. AJAX forms don't reload, so they're excluded.
(function preserveScrollPosition() {
    const root = document.querySelector('[data-preserve-scroll]');
    if (!root) {
        return;
    }

    if ('scrollRestoration' in window.history) {
        window.history.scrollRestoration = 'manual';
    }

    const storageKey = 'jca:scroll:' + window.location.pathname;
    const saved = sessionStorage.getItem(storageKey);
    sessionStorage.removeItem(storageKey);

    if (saved !== null && !document.querySelector('[data-flash-errors]')) {
        window.scrollTo(0, parseInt(saved, 10) || 0);
    }

    root.addEventListener('submit', (event) => {
        if (event.target.closest('form[data-ajax]')) {
            return;
        }
        sessionStorage.setItem(storageKey, String(window.scrollY));
    }, true);
})();

// Progressive-enhancement AJAX for the configuration page ([data-ajax-config]).
// Any form[data-ajax] submits in the background; the server returns just the
// re-rendered section, which we swap in place — no full reload, no scroll jump.
// Success shows a corner toast; validation errors render inline in the section.
// Any failure falls back to a normal submit so the admin is never stuck.
(function ajaxConfigForms() {
    const root = document.querySelector('[data-ajax-config]');
    if (!root) {
        return;
    }

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const showToast = (message, isError = false) => {
        document.querySelectorAll('[data-ajax-toast]').forEach((node) => node.remove());
        const toast = document.createElement('div');
        toast.setAttribute('data-ajax-toast', '');
        toast.setAttribute('role', isError ? 'alert' : 'status');
        toast.className = 'fixed bottom-5 right-5 z-50 flex max-w-sm items-start gap-3 rounded-xl border p-4 shadow-2xl transition-all duration-300 '
            + (isError ? 'border-brand-red-600 bg-red-50 text-brand-red-600' : 'border-emerald-200 bg-white text-success-700');
        const text = document.createElement('p');
        text.className = 'text-sm font-bold';
        text.textContent = message;
        toast.appendChild(text);
        document.body.appendChild(toast);
        const remove = () => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(12px)';
            setTimeout(() => toast.remove(), 300);
        };
        toast.addEventListener('click', remove);
        setTimeout(remove, isError ? 6000 : 4500);
    };

    const clearSectionErrors = (section) => {
        section?.querySelector('[data-section-errors]')?.remove();
    };

    const showSectionErrors = (section, messages) => {
        if (!section) {
            showToast(messages[0] ?? 'Please check your input.', true);
            return;
        }
        clearSectionErrors(section);
        const box = document.createElement('div');
        box.setAttribute('data-section-errors', '');
        box.setAttribute('role', 'alert');
        box.className = 'mx-5 mt-4 rounded-lg border border-brand-red-600 bg-red-50 p-4 text-brand-red-600 sm:mx-6';
        const title = document.createElement('p');
        title.className = 'font-bold';
        title.textContent = 'Please correct the following:';
        const list = document.createElement('ul');
        list.className = 'mt-2 list-disc space-y-1 pl-5 text-sm';
        messages.forEach((message) => {
            const item = document.createElement('li');
            item.textContent = message;
            list.appendChild(item);
        });
        box.append(title, list);
        const header = section.querySelector(':scope > div');
        section.insertBefore(box, header ? header.nextSibling : section.firstChild);
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    root.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-ajax]');
        if (!form) {
            return;
        }
        event.preventDefault();

        const section = form.closest('[data-section]');
        clearSectionErrors(section);

        const buttons = form.querySelectorAll('button[type="submit"], button:not([type])');
        buttons.forEach((button) => { button.disabled = true; });

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token,
                },
            });

            if (response.status === 422) {
                const payload = await response.json().catch(() => ({}));
                const messages = payload.errors ? Object.values(payload.errors).flat() : [payload.message || 'Please check your input.'];
                showSectionErrors(section, messages);
                return;
            }

            if (!response.ok) {
                showToast('Something went wrong. Please try again.', true);
                return;
            }

            const payload = await response.json();
            // Clear the submitted form on success. When the form lives inside
            // the swapped section it is replaced anyway; when it sits outside
            // (e.g. a composer that updates a separate list/table) this resets
            // it in place. CSS-only toggles reset with it.
            try { form.reset(); } catch (error) { /* detached form — ignore */ }
            // If the form lives inside a modal dialog, close it on success.
            form.closest('dialog')?.close();
            if (payload.section && payload.html) {
                const target = document.querySelector('[data-section="' + payload.section + '"]');
                if (target) {
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = payload.html.trim();
                    const fresh = wrapper.firstElementChild;
                    if (fresh) {
                        target.replaceWith(fresh);
                    }
                }
            }
            if (payload.message) {
                showToast(payload.message, false);
            }
        } catch (error) {
            // Network or unexpected failure — fall back to a normal submit
            // (and preserve scroll) so the admin is never left stuck.
            sessionStorage.setItem('jca:scroll:' + window.location.pathname, String(window.scrollY));
            form.submit();
        } finally {
            buttons.forEach((button) => { button.disabled = false; });
        }
    });
})();

// Announcement preview modal on the admin Communications page ([data-communications]).
// Clicking an "Open" button fetches the announcement over AJAX and shows it in a
// dialog instead of navigating away. Delegated from the page root so it keeps
// working after the announcements table is swapped in by AJAX pagination/create.
(function communicationsPreview() {
    const root = document.querySelector('[data-communications]');
    const dialog = document.getElementById('announcement-preview');
    if (!root || !dialog) {
        return;
    }

    const field = (name) => dialog.querySelector('[data-preview-' + name + ']');
    const titleEl = field('title');
    const statusEl = field('status');
    const dateEl = field('date');
    const authorEl = field('author');
    const bodyEl = field('body');
    const audienceWrap = field('audience-wrap');
    const audiencesEl = field('audiences');
    const linkEl = field('link');
    const loadingEl = field('loading');
    const errorEl = field('error');
    const contentEl = field('content');

    const show = (node) => node && node.classList.remove('hidden');
    const hide = (node) => node && node.classList.add('hidden');
    const close = () => { if (dialog.open) dialog.close(); };

    dialog.querySelector('[data-preview-close]')?.addEventListener('click', close);
    dialog.addEventListener('click', (event) => { if (event.target === dialog) close(); });

    root.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-preview-url]');
        if (!trigger) {
            return;
        }
        event.preventDefault();

        titleEl.textContent = ' ';
        hide(contentEl);
        hide(errorEl);
        show(loadingEl);
        if (!dialog.open) {
            dialog.showModal();
        }

        try {
            const response = await fetch(trigger.getAttribute('data-preview-url'), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) {
                throw new Error('load failed');
            }
            const data = await response.json();

            titleEl.textContent = data.title || 'Announcement';
            statusEl.textContent = data.statusLabel || '';
            statusEl.className = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold '
                + (data.status === 'published' ? 'bg-emerald-50 text-success-700' : 'bg-warning-100 text-warning-800');
            dateEl.textContent = data.publishedAt ? ('Published ' + data.publishedAt) : 'Draft — not yet published';
            if (data.author) {
                authorEl.textContent = 'By ' + data.author;
                show(authorEl);
            } else {
                hide(authorEl);
            }
            bodyEl.textContent = data.body || '';
            const deliveryEl = field('delivery');
            if (deliveryEl) {
                deliveryEl.textContent = data.delivery || '';
            }
            const smsWrap = field('sms-wrap');
            if (data.smsBody) {
                field('sms').textContent = data.smsBody;
                show(smsWrap);
            } else {
                hide(smsWrap);
            }

            audiencesEl.innerHTML = '';
            if (Array.isArray(data.audiences) && data.audiences.length) {
                data.audiences.forEach((label) => {
                    const chip = document.createElement('span');
                    chip.className = 'inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600';
                    chip.textContent = label;
                    audiencesEl.appendChild(chip);
                });
                show(audienceWrap);
            } else {
                hide(audienceWrap);
            }

            if (linkEl) {
                linkEl.href = data.url || '#';
                (data.url ? show : hide)(linkEl);
            }

            hide(loadingEl);
            show(contentEl);
        } catch (error) {
            hide(loadingEl);
            errorEl.textContent = 'Could not load this announcement. Please try again.';
            show(errorEl);
        }
    });
})();

// AJAX pagination for the Communications sent history. Intercepts pagination
// links and swaps just that section in place; falls back to normal navigation.
(function communicationsPagination() {
    const root = document.querySelector('[data-communications]');
    if (!root) {
        return;
    }

    root.addEventListener('click', async (event) => {
        const link = event.target.closest('[data-section="history"] nav a[href]');
        if (!link || !root.contains(link)) {
            return;
        }
        if (link.getAttribute('aria-disabled') === 'true') {
            event.preventDefault();
            return;
        }
        event.preventDefault();

        try {
            const response = await fetch(link.href, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) {
                throw new Error('load failed');
            }
            const payload = await response.json();
            const target = document.querySelector('[data-section="' + payload.section + '"]');
            if (payload.html && target) {
                const wrapper = document.createElement('div');
                wrapper.innerHTML = payload.html.trim();
                const fresh = wrapper.firstElementChild;
                if (fresh) {
                    target.replaceWith(fresh);
                    fresh.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        } catch (error) {
            window.location.href = link.href;
        }
    });
})();

// ─────────────────────────────────────────────────────────────────────────────
// In-app Back navigation (UX overhaul)
//
// Problem fixed: the old Back button relied on document.referrer, but the app
// sends `Referrer-Policy: no-referrer`, so the referrer was always empty and
// Back always jumped to the Dashboard.
//
// Each browser tab now keeps a small trail of the pages visited inside the
// workspace (sessionStorage, same-origin paths only). Back returns to the page
// the user actually came from, with its search terms, filters, page number and
// tab (the full query string) intact. Revisiting a page that is already in the
// trail truncates the trail back to it, so Edit -> Save -> Details -> Back goes
// to the list, never back into the form. Pages opened directly fall back to the
// server-computed parent page (see App\Modules\Shared\Navigation\BackDestination).
// Sidebar links start a fresh trail because they switch to another module.
// ─────────────────────────────────────────────────────────────────────────────
(function backNavigation() {
    const TRAIL_KEY = 'jca.nav.trail.v1';
    const STATE_KEY = 'jca.nav.state.v1';
    const RESET_KEY = 'jca.nav.reset.v1';
    const MAX_TRAIL = 30;
    const MAX_STATE = 60;

    const storage = (() => {
        try {
            const probe = '__jca_probe__';
            window.sessionStorage.setItem(probe, '1');
            window.sessionStorage.removeItem(probe);
            return window.sessionStorage;
        } catch (error) {
            return null;
        }
    })();

    const readJson = (key, fallback) => {
        if (!storage) {
            return fallback;
        }
        try {
            const value = JSON.parse(storage.getItem(key) ?? 'null');
            return value ?? fallback;
        } catch (error) {
            return fallback;
        }
    };

    const writeJson = (key, value) => {
        if (!storage) {
            return;
        }
        try {
            storage.setItem(key, JSON.stringify(value));
        } catch (error) {
            // Storage full or disabled: Back simply uses the server fallback.
        }
    };

    // Only ever navigate to same-origin, path-relative URLs (no open redirect).
    const safeLocalUrl = (value) => {
        if (typeof value !== 'string' || value === '') {
            return null;
        }
        try {
            const url = new URL(value, window.location.origin);
            if (url.origin !== window.location.origin) {
                return null;
            }
            return url.pathname + url.search;
        } catch (error) {
            return null;
        }
    };

    const pageTitle = () => (document.title || '').split(' | ')[0].trim();

    const readTrail = () => {
        const trail = readJson(TRAIL_KEY, []);
        return Array.isArray(trail)
            ? trail.filter((entry) => entry && typeof entry.path === 'string' && safeLocalUrl(entry.url))
            : [];
    };

    const recordVisit = () => {
        const page = document.querySelector('[data-jca-page]');
        if (!page) {
            return;
        }

        const current = {
            path: window.location.pathname,
            url: window.location.pathname + window.location.search,
            title: pageTitle(),
        };

        let trail = readTrail();
        const startFresh = page.hasAttribute('data-jca-page-root') || (storage && storage.getItem(RESET_KEY) === '1');
        if (storage) {
            storage.removeItem(RESET_KEY);
        }

        if (startFresh) {
            trail = [];
        } else {
            const existing = trail.findIndex((entry) => entry.path === current.path);
            if (existing !== -1) {
                trail = trail.slice(0, existing);
            }
        }

        trail.push(current);
        writeJson(TRAIL_KEY, trail.slice(-MAX_TRAIL));

        // Remember the last state (query string) of every page, so fallbacks and
        // "Back to <list>" links can restore filters and pagination.
        const state = readJson(STATE_KEY, {});
        const cleanState = state && typeof state === 'object' && !Array.isArray(state) ? state : {};
        delete cleanState[current.path];
        cleanState[current.path] = current.url;
        const keys = Object.keys(cleanState);
        keys.slice(0, Math.max(0, keys.length - MAX_STATE)).forEach((key) => delete cleanState[key]);
        writeJson(STATE_KEY, cleanState);

        return trail;
    };

    const restoredUrlFor = (href) => {
        const local = safeLocalUrl(href);
        if (!local) {
            return null;
        }
        const path = new URL(local, window.location.origin).pathname;
        const state = readJson(STATE_KEY, {});
        // Only restore when the link itself carries no query of its own.
        if (new URL(local, window.location.origin).search !== '') {
            return local;
        }
        return safeLocalUrl(state?.[path]) ?? local;
    };

    const enhanceBackControls = (trail) => {
        const previous = trail.length > 1 ? trail[trail.length - 2] : null;

        document.querySelectorAll('[data-jca-back]').forEach((link) => {
            const fallback = restoredUrlFor(link.dataset.fallbackUrl || link.getAttribute('href') || '');
            const target = previous ? safeLocalUrl(previous.url) : fallback;
            if (!target) {
                return;
            }
            link.setAttribute('href', target);

            const name = previous?.title;
            const text = name ? `Back to ${name}` : 'Back';
            const label = link.querySelector('[data-jca-back-label]');
            if (label) {
                label.textContent = text;
            }
            link.setAttribute('title', text);
            link.setAttribute('aria-label', text);
        });

        // Keep the highlighted current location visible when the bar overflows.
        document.querySelectorAll('[data-jca-location-bar]').forEach((bar) => {
            bar.scrollLeft = bar.scrollWidth;
        });

        // Explicit "Back to <list>" links keep their destination but regain the
        // list's last search, filters and page.
        document.querySelectorAll('a[data-jca-restore]').forEach((link) => {
            const restored = restoredUrlFor(link.getAttribute('href') || '');
            if (restored) {
                link.setAttribute('href', restored);
            }
        });
    };

    const run = () => {
        const trail = recordVisit();
        if (trail) {
            enhanceBackControls(trail);
        }
    };

    run();

    // Pages restored from the browser's back/forward cache do not reload, so the
    // trail is re-synchronised to keep browser Back and in-app Back consistent.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            run();
        }
    });

    document.addEventListener('click', (event) => {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }
        const rootLink = event.target.closest('a[data-jca-nav-root]');
        if (rootLink && storage) {
            storage.setItem(RESET_KEY, '1');
        }
    });

    document.addEventListener('submit', (event) => {
        if (event.target.closest('form[data-jca-signout]') && storage) {
            [TRAIL_KEY, STATE_KEY, RESET_KEY].forEach((key) => storage.removeItem(key));
        }
    });
})();

// ─────────────────────────────────────────────────────────────────────────────
// Confirmation for important or destructive actions.
// Add data-confirm="Message" to a <form> (checked on submit) or to a button /
// link (checked on click). Works without Alpine.
// ─────────────────────────────────────────────────────────────────────────────
(function confirmations() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        const message = event.submitter?.dataset?.confirm || form.dataset.confirm;
        if (message && !window.confirm(message)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }
        // Prevent accidental double submissions and show progress.
        if (form.hasAttribute('data-loading-submit')) {
            const button = event.submitter ?? form.querySelector('[type="submit"]');
            if (button && !button.disabled) {
                window.setTimeout(() => {
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                    if (button.dataset.loadingText) {
                        button.textContent = button.dataset.loadingText;
                    }
                }, 0);
            }
        }
    }, true);

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('a[data-confirm]');
        if (trigger && !window.confirm(trigger.dataset.confirm)) {
            event.preventDefault();
        }
    });
})();
