<x-layouts.app title="Reissue Temporary Password">
<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 12mm;
    }

    html, body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }

    body * {
        visibility: hidden !important;
    }

    #reissued-credentials-slip,
    #reissued-credentials-slip * {
        visibility: visible !important;
    }

    #reissued-credentials-slip {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 18px !important;
        border: 1px solid #d1d5db !important;
        border-radius: 8px !important;
        box-shadow: none !important;
        background: white !important;
        color: #172554 !important;
        box-sizing: border-box !important;
    }

    #reissued-credentials-slip dl {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 16px !important;
    }

    #reissued-credentials-slip button {
        display: none !important;
    }
}
</style>
    <div class="mx-auto max-w-3xl space-y-6">

        <header>
            <h1 class="text-2xl font-black text-brand-navy-900">
                Reissue Temporary Password
            </h1>
            <p class="mt-2 text-sm text-muted">
                Search for an existing account, verify the account holder's
                identity, and issue a new temporary password.
            </p>
        </header>

        @if ($errors->any())
            <div class="rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-bold">Please correct the following:</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('reissued_access'))
            @php
                $access = session('reissued_access');
            @endphp

            <section id="reissued-credentials-slip" class="jca-card space-y-4 border-2 border-green-600 p-5">
                <h2 class="text-lg font-black text-brand-navy-900">
                    Temporary Password Successfully Reissued
                </h2>

                <p class="text-sm text-muted">
                    This password is displayed only once.
                    Give these credentials directly to the verified account holder.
                </p>

                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-bold text-muted">Account Name</dt>
                        <dd class="font-bold text-ink">
                            {{ $access['name'] }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-bold text-muted">Account ID</dt>
                        <dd class="font-mono font-bold text-ink">
                            {{ $access['account_id'] }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-bold text-muted">Temporary Password</dt>
                        <dd class="select-all font-mono text-xl font-black text-brand-navy-900">
                            {{ $access['temporary_password'] }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-bold text-muted">Expires</dt>
                        <dd class="font-bold text-ink">
                            {{ $access['expires_at'] }}
                        </dd>
                    </div>
                </dl>

                <p class="text-sm font-semibold text-green-800">
                    The account holder must create a permanent password after signing in.
                </p>

                <button type="button"
                        onclick="window.print()"
                        class="jca-button jca-button-secondary print:hidden">
                    Print Credentials
                </button>
            </section>
        @endif

        <section
            class="jca-card p-5 sm:p-6"
            data-lookup-url="{{ route('identity.users.reissue.lookup') }}"
            x-data="{
                accountId: '',
                result: null,
                loading: false,
                confirmed: false,
                timer: null,
                requestNumber: 0,

                search() {
                    clearTimeout(this.timer);
                    const number = ++this.requestNumber;

                    this.result = null;
                    this.confirmed = false;
                    this.loading = false;

                    const id = this.accountId.trim().toUpperCase();

                    if (!id) return;

                    this.timer = setTimeout(async () => {
                        if (number !== this.requestNumber) return;

                        this.loading = true;

                        try {
                            const url = new URL(this.$root.dataset.lookupUrl);
                            url.searchParams.set('account_id', id);

                            const response = await fetch(url.toString(), {
                                method: 'GET',
                                credentials: 'same-origin',
                                headers: {
                                    'Accept': 'application/json'
                                }
                            });

                            if (!response.ok) {
                                throw new Error('Lookup failed');
                            }

                            const data = await response.json();

                            if (number === this.requestNumber) {
                                this.result = data;
                            }
                        } catch (error) {
                            if (number === this.requestNumber) {
                                this.result = {
                                    found: false,
                                    error: true
                                };
                            }
                        } finally {
                            if (number === this.requestNumber) {
                                this.loading = false;
                            }
                        }
                    }, 450);
                }
            }"
        >

            <form method="POST"
                  action="{{ route('identity.users.reissue.store') }}"
                  autocomplete="off"
                  class="space-y-5"
                  onsubmit="return confirm('Reissue temporary password? The previous password will stop working immediately.');">

                @csrf

                <div>
                    <label for="account_id"
                           class="mb-2 block text-sm font-bold text-brand-navy-900">
                        Enter Account ID
                    </label>

                    <input
                        id="account_id"
                        name="account_id"
                        type="text"
                        x-model="accountId"
                        x-on:input="search()"
                        placeholder="Parent ID, Student ID, or Staff ID"
                        maxlength="40"
                        class="jca-input w-full"
                        required
                    >

                    <p class="mt-2 text-xs text-muted">
                        Enter the ID to automatically display the account holder's name.
                    </p>
                </div>

                <p x-show="loading"
                   x-cloak
                   class="text-sm text-muted">
                    Searching account...
                </p>

                <div x-show="result && !loading" x-cloak>

                    <div x-show="result?.found"
                         class="rounded-xl border border-border bg-canvas p-4">

                        <h2 class="mb-3 text-sm font-black text-brand-navy-900">
                            Account Found
                        </h2>

                        <dl class="space-y-3 text-sm">

                            <div>
                                <dt class="font-semibold text-muted">
                                    Account Name
                                </dt>
                                <dd class="font-bold text-ink"
                                    x-text="result?.name">
                                </dd>
                            </div>

                            <div>
                                <dt class="font-semibold text-muted">
                                    Account Role
                                </dt>
                                <dd class="font-bold text-ink"
                                    x-text="result?.role">
                                </dd>
                            </div>

                            <div>
                                <dt class="font-semibold text-muted">
                                    Account Status
                                </dt>
                                <dd class="font-bold text-ink"
                                    x-text="result?.status">
                                </dd>
                            </div>

                        </dl>
                    </div>

                    <p x-show="result && !result.found"
                       class="text-sm font-semibold text-red-700">
                        Account not found or not accessible.
                    </p>

                </div>

                <label x-show="result?.found"
                       x-cloak
                       class="flex items-start gap-3 text-sm text-ink">

                    <input
                        type="checkbox"
                        name="identity_confirmed"
                        value="1"
                        x-model="confirmed"
                        class="mt-1"
                    >

                    <span>
                        I confirm that I have verified the account holder's identity
                        using the school's authorized verification procedure.
                    </span>
                </label>

                <button
                    type="submit"
                    class="jca-button w-full"
                    x-bind:disabled="!result?.found || !confirmed || loading"
                >
                    Verify & Reissue Temporary Password
                </button>

            </form>
        </section>

    </div>
</x-layouts.app>
