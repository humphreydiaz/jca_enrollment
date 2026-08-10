<x-layouts.app title="Reports">
    <div class="mx-auto max-w-7xl space-y-8">
        <header><h1 class="text-3xl font-black text-brand-navy-900">Operational reports</h1><p class="mt-2 max-w-3xl text-muted">Each report uses a fixed field allowlist and your current role scope. These development layouts are operational outputs, not yet approved official forms.</p></header>
        <section aria-labelledby="report-catalog-title"><h2 id="report-catalog-title" class="text-xl font-black text-brand-navy-900">Available reports</h2><div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($reports as $code => $report)
                <a class="jca-card block p-5 hover:border-brand-navy-700" href="{{ route('reporting.show', $code) }}"><h3 class="font-black text-brand-navy-900">{{ $report['label'] }}</h3><p class="mt-2 text-sm leading-6 text-muted">{{ $report['description'] }}</p></a>
            @endforeach
        </div></section>
        <section class="jca-card p-6" aria-labelledby="exports-title"><h2 id="exports-title" class="text-xl font-black text-brand-navy-900">My private exports</h2><div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-border"><th class="p-3">Report</th><th class="p-3">Status</th><th class="p-3">Rows</th><th class="p-3">Expires</th><th class="p-3">Actions</th></tr></thead><tbody>
            @forelse($exports as $export)<tr class="border-b border-border"><td class="p-3 font-bold">{{ $reports[$export->report_code]['label'] ?? $export->report_code }}</td><td class="p-3"><x-ui.status-badge :status="$export->status" /></td><td class="p-3">{{ $export->row_count ?? '—' }}</td><td class="p-3">{{ $export->expires_at->timezone(config('app.timezone'))->format('d M Y, g:i A') }}</td><td class="p-3"><div class="flex flex-wrap gap-2">@if($export->status === 'completed' && $export->deleted_at === null && $export->expires_at->isFuture())<a class="font-bold text-brand-navy-700 underline" href="{{ route('reporting.exports.download', $export) }}">Download CSV</a>@endif @if($export->deleted_at === null)<form method="POST" action="{{ route('reporting.exports.destroy', $export) }}">@csrf @method('DELETE')<button class="font-bold text-brand-red-600 underline" type="submit">Delete file</button></form>@endif</div></td></tr>
            @empty<tr><td colspan="5" class="p-5 text-muted">No exports requested yet.</td></tr>@endforelse
        </tbody></table></div></section>
    </div>
</x-layouts.app>
