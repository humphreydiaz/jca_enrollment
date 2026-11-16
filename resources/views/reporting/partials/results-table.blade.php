{{-- Report rows with a tick box each; ticked rows feed the "Print selected rows" form (id report-print-selected). --}}
@php($moneyColumns = \App\Modules\Reporting\Reports\ReportCatalog::MONEY_COLUMNS)
<section class="jca-card overflow-hidden" aria-label="Report results">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border bg-brand-blue-100">
                    <th scope="col" class="w-10 p-3">
                        <label class="sr-only" for="report-select-page">Select every row on this page</label>
                        <input type="checkbox" id="report-select-page" data-select-page class="size-4 rounded border-border">
                    </th>
                    @foreach($columns as $field => $label)
                        @php($isSorted = ($filters['sort'] ?? null) === $field)
                        @php($nextDirection = $isSorted && ($filters['direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc')
                        <th scope="col" class="whitespace-nowrap p-3 font-black text-brand-navy-900 {{ in_array($field, $moneyColumns, true) ? 'text-right' : '' }}"
                            @if($isSorted) aria-sort="{{ ($filters['direction'] ?? 'asc') === 'desc' ? 'descending' : 'ascending' }}" @endif>
                            <a class="inline-flex items-center gap-1 hover:underline"
                               href="{{ route('reporting.show', [$reportCode] + array_merge(request()->except(['page', 'sort', 'direction']), ['sort' => $field, 'direction' => $nextDirection])) }}"
                               title="Sort by {{ $label }}">
                                {{ $label }}
                                @if($isSorted)<span aria-hidden="true">{{ ($filters['direction'] ?? 'asc') === 'desc' ? '▼' : '▲' }}</span>@endif
                            </a>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr class="border-b border-border">
                        <td class="p-3">
                            <label class="sr-only" for="row-{{ $row->row_key }}">Select row {{ $loop->iteration }}</label>
                            <input type="checkbox" id="row-{{ $row->row_key }}" name="rows[]" value="{{ $row->row_key }}" form="report-print-selected" data-row-check class="size-4 rounded border-border">
                        </td>
                        @foreach($columns as $field => $label)
                            <td class="whitespace-nowrap p-3 {{ in_array($field, $moneyColumns, true) ? 'text-right tabular-nums' : '' }}">
                                @if($field === 'status' && filled(data_get($row, $field)))
                                    <x-ui.status-badge :status="data_get($row, $field)" />
                                @elseif(in_array($field, $moneyColumns, true) && filled(data_get($row, $field)))
                                    {{ number_format((float) data_get($row, $field), 2) }}
                                @else
                                    {{ data_get($row, $field) ?? '—' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td class="p-6 text-muted" colspan="{{ count($columns) + 1 }}">No records match the authorized filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
{{ $rows->links() }}
