<x-layouts.app title="Dashboard">
    <div class="space-y-8">
        <section class="jca-card overflow-hidden" aria-labelledby="dashboard-title">
            <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <p class="text-sm font-bold text-brand-orange-500">Welcome back</p>
                    <h1 id="dashboard-title" class="mt-1 text-3xl font-black tracking-tight text-brand-navy-900 sm:text-4xl">{{ auth()->user()->name }}</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-muted sm:text-base">Here is the work currently waiting within your authorized JCA responsibilities.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach (auth()->user()->roles as $role)
                            <x-ui.status-badge tone="info">{{ $role->name }}</x-ui.status-badge>
                        @endforeach
                    </div>
                </div>
                <div class="hidden size-28 place-items-center rounded-full bg-brand-blue-100 text-brand-navy-700 lg:grid" aria-hidden="true">
                    <x-ui.icon name="dashboard" size="size-12" />
                </div>
            </div>
            <div class="h-1.5 bg-gradient-to-r from-brand-navy-700 via-brand-orange-500 to-brand-gold-500"></div>
        </section>

        <section aria-labelledby="next-actions-title">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="next-actions-title" class="text-xl font-black text-brand-navy-900">Attention needed</h2>
                    <p class="mt-1 text-sm text-muted">Live counts from records available to your role.</p>
                </div>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @forelse ($cards as $card)
                    @php
                        $cardIcon = match ($card['label']) {
                            'Enrollment review', 'Applications' => 'enrollment',
                            'SMS follow-up' => 'communications',
                            'Billing work' => 'billing',
                            'User accounts' => 'users',
                            'Awaiting placement' => 'sections',
                            'Support cases' => 'support',
                            'Enrolled students' => 'people',
                            'Needs assessment' => 'finance',
                            'Draft payments' => 'billing',
                            'Permits issued today' => 'check-all',
                            'My students' => 'people',
                            'My children' => 'users',
                            'My enrollment' => 'progress',
                            default => 'dashboard',
                        };
                    @endphp
                    <a href="{{ $card['route'] }}" class="jca-card group flex min-h-44 flex-col p-5 transition hover:-translate-y-0.5 hover:border-brand-navy-700 hover:shadow-lg">
                        <div class="flex items-start justify-between gap-4">
                            <span class="grid size-11 place-items-center rounded-xl bg-brand-blue-100 text-brand-navy-700 transition group-hover:bg-brand-navy-700 group-hover:text-white">
                                <x-ui.icon :name="$cardIcon" />
                            </span>
                            <span class="text-3xl font-black tabular-nums text-brand-navy-900">{{ $card['value'] }}</span>
                        </div>
                        <div class="mt-auto pt-5">
                            <h3 class="font-extrabold text-brand-navy-900">{{ $card['label'] }}</h3>
                            <div class="mt-1 flex items-center justify-between gap-3 text-sm text-muted">
                                <span>{{ $card['detail'] }}</span>
                                <x-ui.icon name="arrow-right" size="size-4" class="shrink-0 text-brand-navy-700 transition group-hover:translate-x-1" />
                            </div>
                        </div>
                    </a>
                @empty
                    <x-ui.empty-state class="sm:col-span-2 xl:col-span-4" icon="dashboard" title="No active workspace" description="This account does not yet have an active portal role. Ask an administrator to review its role assignment." />
                @endforelse
            </div>
        </section>

        @isset($adminData)
            @include('communications.portal._admin-dashboard-sections', $adminData)
        @endisset

        @isset($registrarData)
            @include('communications.portal._registrar-dashboard-sections', $registrarData)
        @endisset

        @isset($accountingData)
            @include('communications.portal._accounting-dashboard-sections', $accountingData)
        @endisset

        <section class="jca-card overflow-hidden" aria-labelledby="announcement-title">
            <div class="flex items-center justify-between gap-4 border-b border-border px-5 py-4 sm:px-6">
                <div>
                    <h2 id="announcement-title" class="text-lg font-black text-brand-navy-900">Latest announcements</h2>
                    <p class="mt-0.5 text-sm text-muted">School updates visible to your account.</p>
                </div>
                <a class="inline-flex items-center gap-2 text-sm font-bold text-brand-navy-700 hover:underline" href="{{ route('communications.index') }}">View all <x-ui.icon name="arrow-right" size="size-4" /></a>
            </div>
            <div class="divide-y divide-border">
                @forelse ($announcements as $announcement)
                    <a href="{{ route('communications.show', $announcement) }}" class="group flex gap-4 px-5 py-5 transition hover:bg-slate-50 sm:px-6">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-warning-100 text-warning-800"><x-ui.icon name="announcement" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-bold text-brand-navy-900 group-hover:underline">{{ $announcement->title }}</span>
                            <span class="mt-1 block text-sm leading-6 text-muted">{{ \Illuminate\Support\Str::limit($announcement->body, 180) }}</span>
                        </span>
                        <x-ui.icon name="arrow-right" size="size-4" class="mt-3 text-slate-400" />
                    </a>
                @empty
                    <div class="p-5 sm:p-6">
                        <x-ui.empty-state icon="announcement" title="No announcements yet" description="New school announcements for your role will appear here." />
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
