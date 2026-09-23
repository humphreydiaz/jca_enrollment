<x-layouts.app title="Financial configuration">
    <div class="space-y-6" data-preserve-scroll data-ajax-config>
        <x-ui.page-header
            title="Financial configuration"
            description="Manage school-year fee rules, schedules, payment options, examination periods, discounts, and controlled print wording."
            eyebrow="Admin workspace"
        />

        <div class="grid items-start gap-6 xl:grid-cols-[15rem_minmax(0,1fr)]">
            <nav class="jca-card sticky top-24 hidden p-3 xl:block" aria-label="Configuration sections">
                @foreach ([
                    ['href' => '#fee-catalog', 'icon' => 'billing', 'label' => 'Fee catalog'],
                    ['href' => '#fee-schedules', 'icon' => 'finance', 'label' => 'Fee schedules'],
                    ['href' => '#payment-plans', 'icon' => 'progress', 'label' => 'Payment plans'],
                    ['href' => '#exam-periods', 'icon' => 'academics', 'label' => 'Exam periods'],
                    ['href' => '#calendar', 'icon' => 'sections', 'label' => 'School calendar'],
                    ['href' => '#discounts', 'icon' => 'announcement', 'label' => 'Discounts'],
                    ['href' => '#print-settings', 'icon' => 'settings', 'label' => 'Print settings'],
                ] as $item)
                    <a href="{{ $item['href'] }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-brand-blue-100 hover:text-brand-navy-900"><x-ui.icon :name="$item['icon']" size="size-4" /> {{ $item['label'] }}</a>
                @endforeach
            </nav>

            <div class="min-w-0 space-y-6">
                @include('billing.admin.partials.fee-catalog')
                @include('billing.admin.partials.fee-schedules')
                @include('billing.admin.partials.payment-plans')
                @include('billing.admin.partials.exam-periods')
                @include('billing.admin.partials.calendar')
                @include('billing.admin.partials.discounts')
                @include('billing.admin.partials.print-settings')
            </div>
        </div>
    </div>
</x-layouts.app>
