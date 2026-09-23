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

Alpine.data('enrollmentReviewWorkspace', (rows = []) => ({
    openRows: [],
    selected: [],
    previewName: '',
    previewUrl: '',

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
            .filter((p) => p.status === 'eligible' && !p.draftCreated)
            .map((p) => p.publicId);
    },

    get eligiblePromotionCount() {
        return promotions.filter((p) => p.status === 'eligible' && !p.draftCreated).length;
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
            this.error = 'This record could not be loaded. Use "Open full page" to read it directly.';
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

// Publish-draft modal on the admin Communications page. Clicking "Publish" on a
// draft row opens a dialog to confirm and optionally pick an SMS template; the
// form submits through the shared [data-ajax] handler, which swaps the table and
// closes the dialog on success. Delegated so it survives table swaps.
(function communicationsPublish() {
    const root = document.querySelector('[data-communications]');
    const dialog = document.getElementById('announcement-publish');
    const form = document.getElementById('announcement-publish-form');
    if (!root || !dialog || !form) {
        return;
    }

    const titleEl = dialog.querySelector('[data-publish-title]');
    const templateSelect = dialog.querySelector('#publish_sms_template');
    const close = () => { if (dialog.open) dialog.close(); };

    dialog.querySelectorAll('[data-publish-close]').forEach((button) => button.addEventListener('click', close));
    dialog.addEventListener('click', (event) => { if (event.target === dialog) close(); });

    root.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-publish-url]');
        if (!trigger) {
            return;
        }
        event.preventDefault();

        form.action = trigger.getAttribute('data-publish-url');
        titleEl.textContent = trigger.getAttribute('data-publish-title') || 'Announcement';
        if (templateSelect) {
            templateSelect.value = '';
        }
        if (!dialog.open) {
            dialog.showModal();
        }
    });
})();

// "Revise" a template version: pre-fill the create-template form with an existing
// version's code, purpose and content so saving produces the next immutable
// version. Delegated so it survives the templates section being swapped.
(function communicationsReviseTemplate() {
    const root = document.querySelector('[data-communications]');
    if (!root) {
        return;
    }

    root.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-revise-template]');
        if (!trigger) {
            return;
        }
        event.preventDefault();

        const code = document.getElementById('template_code');
        const purpose = document.getElementById('template_purpose');
        const content = document.getElementById('template_content');
        if (code) {
            code.value = trigger.getAttribute('data-code') || '';
        }
        if (purpose) {
            purpose.value = trigger.getAttribute('data-purpose') || '';
        }
        if (content) {
            content.value = trigger.getAttribute('data-content') || '';
        }
        document.getElementById('template-create-form')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        content?.focus();
    });
})();

// AJAX pagination for the "Posted announcements" table. Intercepts pagination
// links and swaps just that section in place; falls back to normal navigation.
(function communicationsPagination() {
    const root = document.querySelector('[data-communications]');
    if (!root) {
        return;
    }

    root.addEventListener('click', async (event) => {
        const link = event.target.closest('[data-section="announcements"] nav a[href]');
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
