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
