<x-layouts.app title="School insights">
    <div class="mx-auto max-w-screen-2xl space-y-6">
        <x-ui.page-header
            title="School insights"
            eyebrow="Insights"
            description="Enrollment and collections for a school year, compared with the year before. Figures come from finalized assessments and posted payments." />

        <form method="GET" action="{{ route('reporting.analytics') }}" class="jca-card flex flex-wrap items-end gap-3 p-4">
            <label class="text-sm font-bold">School year
                <select name="year" class="mt-1 block rounded-lg border-border">
                    @foreach ($years as $option)
                        <option value="{{ $option->public_id }}" @selected($insights && $insights['year']->public_id === $option->public_id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </label>
            <x-ui.button type="submit" variant="secondary">Show</x-ui.button>
            @if ($insights)
                <a class="jca-button jca-button-primary" href="{{ route('reporting.analytics.print', ['year' => $insights['year']->public_id]) }}" target="_blank" rel="noopener">Print for the owner</a>
            @endif
        </form>

        @if ($insights)
            @include('reporting.analytics.partials.body')
        @else
            <x-ui.empty-state icon="reports" title="No school year yet" description="Insights appear once a school year is set up in Academics." />
        @endif
    </div>
</x-layouts.app>
