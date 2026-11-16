<x-layouts.app :title="$definition['label'].' report'">
    @php
        $presentation = array_filter([
            'cols' => $pickedColumns,
            'per_page' => $perPage !== 25 ? $perPage : null,
        ]);
        $printQuery = $filters + $presentation;
    @endphp
    <div class="mx-auto max-w-screen-2xl space-y-6">
        <header><p class="text-sm font-bold text-muted">Operational report</p><h1 class="mt-1 text-3xl font-black text-brand-navy-900">{{ $definition['label'] }}</h1><p class="mt-2 text-muted">{{ $definition['description'] }}</p></header>

        <form class="jca-card space-y-5 p-5" method="GET" action="{{ route('reporting.show', $reportCode) }}">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @if(in_array('search', $allowedFilters, true))
                    <label class="text-sm font-bold">Learner name or Student ID<input class="mt-1 w-full rounded-lg border-border" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="e.g. Santos or JCA-26-123"></label>
                @endif
                @if(in_array('school_year', $allowedFilters, true))<label class="text-sm font-bold">School year<select class="mt-1 w-full rounded-lg border-border" name="school_year"><option value="">All school years</option>@foreach($academicYears as $year)<option value="{{ $year->public_id }}" @selected(($filters['school_year'] ?? '') === $year->public_id)>{{ $year->name }}</option>@endforeach</select></label>@endif
                @if(in_array('grade', $allowedFilters, true))<label class="text-sm font-bold">Grade<select class="mt-1 w-full rounded-lg border-border" name="grade"><option value="">All grades</option>@foreach($gradeLevels as $grade)<option value="{{ $grade->public_id }}" @selected(($filters['grade'] ?? '') === $grade->public_id)>{{ $grade->name }}</option>@endforeach</select></label>@endif
                @if(in_array('section', $allowedFilters, true))<label class="text-sm font-bold">Section<select class="mt-1 w-full rounded-lg border-border" name="section"><option value="">All sections</option>@foreach($sections as $section)<option value="{{ $section->public_id }}" @selected(($filters['section'] ?? '') === $section->public_id)>{{ $section->gradeLevel->name }} – {{ $section->name }} ({{ $section->academicYear->name }})</option>@endforeach</select></label>@endif
                @if(in_array('status', $allowedFilters, true))
                    <label class="text-sm font-bold">Status<select class="mt-1 w-full rounded-lg border-border" name="status"><option value="">All statuses</option>@foreach($statusOptions as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->headline() }}</option>@endforeach</select></label>
                @endif
                @if(in_array('band', $allowedFilters, true))
                    <label class="text-sm font-bold">Final grade range<select class="mt-1 w-full rounded-lg border-border" name="band"><option value="">Any grade</option>@foreach(\App\Modules\Reporting\Reports\ReportCatalog::GRADE_BANDS as $value => $band)<option value="{{ $value }}" @selected(($filters['band'] ?? '') === $value)>{{ $band['label'] }}</option>@endforeach</select></label>
                @endif
                @if(in_array('min_grade', $allowedFilters, true))
                    <div class="text-sm font-bold">
                        <span id="custom-range-label">Or a custom range</span>
                        <div class="mt-1 flex items-center gap-2" role="group" aria-labelledby="custom-range-label">
                            <label class="sr-only" for="min_grade">Lowest final grade</label>
                            <input class="w-full rounded-lg border-border" id="min_grade" type="number" name="min_grade" min="0" max="100" step="0.01" value="{{ $filters['min_grade'] ?? '' }}" placeholder="From">
                            <span aria-hidden="true">–</span>
                            <label class="sr-only" for="max_grade">Highest final grade</label>
                            <input class="w-full rounded-lg border-border" id="max_grade" type="number" name="max_grade" min="0" max="100" step="0.01" value="{{ $filters['max_grade'] ?? '' }}" placeholder="To">
                        </div>
                    </div>
                @endif
                @if(in_array('date_from', $allowedFilters, true))<label class="text-sm font-bold">From date<input class="mt-1 w-full rounded-lg border-border" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>@endif
                @if(in_array('date_to', $allowedFilters, true))<label class="text-sm font-bold">To date<input class="mt-1 w-full rounded-lg border-border" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>@endif
                <label class="text-sm font-bold">Sort by<select class="mt-1 w-full rounded-lg border-border" name="sort"><option value="">Default order</option>@foreach($allColumns as $field => $label)<option value="{{ $field }}" @selected(($filters['sort'] ?? '') === $field)>{{ $label }}</option>@endforeach</select></label>
                <label class="text-sm font-bold">Order<select class="mt-1 w-full rounded-lg border-border" name="direction"><option value="asc" @selected(($filters['direction'] ?? 'asc') === 'asc')>Low to high / A to Z</option><option value="desc" @selected(($filters['direction'] ?? '') === 'desc')>High to low / Z to A</option></select></label>
                <label class="text-sm font-bold">Rows per page<select class="mt-1 w-full rounded-lg border-border" name="per_page">@foreach(\App\Modules\Reporting\Http\Requests\ReportFilterRequest::PER_PAGE_OPTIONS as $option)<option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>@endforeach</select></label>
            </div>

            <details class="rounded-lg border border-border p-4" @if($pickedColumns) open @endif>
                <summary class="cursor-pointer text-sm font-black text-brand-navy-900">Columns to show and print ({{ count($columns) }} of {{ count($allColumns) }})</summary>
                <fieldset class="mt-3">
                    <legend class="sr-only">Columns to show and print</legend>
                    <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
                        @foreach($allColumns as $field => $label)
                            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="cols[]" value="{{ $field }}" @checked(array_key_exists($field, $columns)) class="size-4 rounded border-border"> {{ $label }}</label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-muted">Untick the columns you do not need. Leaving all ticked shows every column.</p>
                </fieldset>
            </details>

            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button type="submit">Apply filters</x-ui.button>
                <a class="jca-button jca-button-secondary" href="{{ route('reporting.show', $reportCode) }}">Clear</a>
                <span class="text-sm text-muted">{{ number_format($rows->total()) }} {{ \Illuminate\Support\Str::plural('record', $rows->total()) }} match.</span>
            </div>
        </form>

        {{-- Printing and export use exactly the filters and columns shown above. --}}
        <div class="jca-card flex flex-wrap items-center gap-3 p-4">
            @if($reportCode === 'enrollments')
                <a class="jca-button jca-button-secondary" href="{{ route('reporting.print', [$reportCode] + $filters) }}" target="_blank" rel="noopener">Print enrolment summary</a>
                <a class="jca-button jca-button-secondary" href="{{ route('reporting.print', [$reportCode] + $printQuery + ['layout' => 'detail']) }}" target="_blank" rel="noopener">Print all matching records</a>
            @else
                <a class="jca-button jca-button-secondary" href="{{ route('reporting.print', [$reportCode] + $printQuery) }}" target="_blank" rel="noopener">Print all matching records</a>
            @endif
            <form method="GET" action="{{ route('reporting.print', $reportCode) }}" target="_blank" id="report-print-selected" data-print-selected>
                @foreach($filters as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                @foreach($pickedColumns ?? [] as $field)<input type="hidden" name="cols[]" value="{{ $field }}">@endforeach
                <button type="submit" class="jca-button jca-button-primary" data-print-selected-button>Print selected rows (<span data-selected-count>0</span>)</button>
            </form>
            <form method="POST" action="{{ route('reporting.exports.store', [$reportCode] + $printQuery) }}">@csrf<x-ui.button type="submit" variant="secondary">Queue private CSV</x-ui.button></form>
            <p class="w-full text-xs text-muted">Tick rows in the table to print only those learners. Printing and CSV use the same filters and columns.</p>
        </div>
        <script>
            (() => {
                const form = document.querySelector('[data-print-selected]');
                if (! form) { return; }
                const button = form.querySelector('[data-print-selected-button]');
                const count = form.querySelector('[data-selected-count]');
                const boxes = () => Array.from(document.querySelectorAll('[data-row-check]'));
                const refresh = () => {
                    const ticked = boxes().filter((box) => box.checked).length;
                    count.textContent = ticked;
                    button.disabled = ticked === 0;
                };
                document.addEventListener('change', (event) => {
                    if (event.target.matches('[data-select-page]')) {
                        boxes().forEach((box) => { box.checked = event.target.checked; });
                    }
                    if (event.target.matches('[data-row-check], [data-select-page]')) { refresh(); }
                });
                refresh();
            })();
        </script>

        {{-- ═══════════════════════════════════════════════════════════
             ENROLLMENT REPORT — SUMMARY + DETAIL TABS
        ═══════════════════════════════════════════════════════════ --}}
        @if($reportCode === 'enrollments' && isset($enrollmentSummary))
            <div x-data="{ tab: 'summary' }" class="space-y-4">
                {{-- Tab controls --}}
                <div class="flex gap-1 rounded-lg bg-surface p-1 border border-border" role="tablist" aria-label="Report view">
                    <button
                        @click="tab = 'summary'"
                        :class="tab === 'summary' ? 'bg-brand-navy-900 text-white shadow-sm' : 'text-muted hover:text-brand-navy-900'"
                        class="rounded-md px-4 py-2 text-sm font-bold transition-colors"
                        role="tab"
                        :aria-selected="tab === 'summary'"
                        id="tab-summary"
                    >
                        Enrolment Summary
                    </button>
                    <button
                        @click="tab = 'detail'"
                        :class="tab === 'detail' ? 'bg-brand-navy-900 text-white shadow-sm' : 'text-muted hover:text-brand-navy-900'"
                        class="rounded-md px-4 py-2 text-sm font-bold transition-colors"
                        role="tab"
                        :aria-selected="tab === 'detail'"
                        id="tab-detail"
                    >
                        Detail Records
                    </button>
                </div>

                {{-- ── Summary panel ─────────────────────────────── --}}
                <div x-show="tab === 'summary'" x-cloak role="tabpanel" aria-labelledby="tab-summary">
                    <section class="jca-card overflow-hidden" aria-label="Enrolment data summary">
                        <div class="enrolment-summary">
                            {{-- Header --}}
                            <div class="enrolment-summary__header">
                                <div class="enrolment-summary__logo">
                                    <img src="{{ asset('images/logo/jca_logo.png') }}" alt="" aria-hidden="true">
                                </div>
                                <div class="enrolment-summary__school-info">
                                    <h2 class="enrolment-summary__school-name">JCSGO CHRISTIAN ACADEMY R1 INC.</h2>
                                    <p>Almazan St. Libsong West, Lingayen, Pangasinan</p>
                                    <p>jcaregion1@yahoo.com</p>
                                    <p>Tel No. 075-542-4973</p>
                                </div>
                            </div>

                            {{-- Title --}}
                            <div class="enrolment-summary__title">
                                <h3>Enrolment Data</h3>
                                @if($enrollmentSummary['school_year_name'])
                                    <p>S.Y. — {{ $enrollmentSummary['school_year_name'] }}</p>
                                @endif
                                <p class="enrolment-summary__as-of">AS OF {{ strtoupper(now()->timezone(config('app.timezone'))->format('F Y')) }}</p>
                            </div>

                            {{-- Stage tables --}}
                            @php
                                $stageLabels = [
                                    'Pre-elementary' => 'A. PRE-ELEMENTARY',
                                    'Elementary' => 'B. ELEMENTARY',
                                    'Junior High School' => 'C. JUNIOR HIGH SCHOOL',
                                ];
                                $grandMale = 0;
                                $grandFemale = 0;
                                $grandTotal = 0;

                                // Pre-elem + Elem for ELEM GRAND TOTAL
                                $elemGrandMale = 0;
                                $elemGrandFemale = 0;
                                $elemGrandTotal = 0;
                            @endphp

                            @foreach($enrollmentSummary['stages'] as $stageName => $grades)
                                @php
                                    $stageMale = collect($grades)->sum('male');
                                    $stageFemale = collect($grades)->sum('female');
                                    $stageTotal = collect($grades)->sum('total');

                                    $grandMale += $stageMale;
                                    $grandFemale += $stageFemale;
                                    $grandTotal += $stageTotal;

                                    if (in_array($stageName, ['Pre-elementary', 'Elementary'])) {
                                        $elemGrandMale += $stageMale;
                                        $elemGrandFemale += $stageFemale;
                                        $elemGrandTotal += $stageTotal;
                                    }
                                @endphp

                                <div class="enrolment-summary__stage">
                                    <h4 class="enrolment-summary__stage-label">{{ $stageLabels[$stageName] ?? strtoupper($stageName) }}</h4>
                                    <table class="enrolment-summary__table">
                                        <thead>
                                            <tr>
                                                <th class="enrolment-summary__th-grade">Grade Level</th>
                                                <th class="enrolment-summary__th-num">Male</th>
                                                <th class="enrolment-summary__th-num">Female</th>
                                                <th class="enrolment-summary__th-num">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($grades as $gradeRow)
                                                <tr>
                                                    <td>{{ $gradeRow['grade'] }}</td>
                                                    <td class="enrolment-summary__num">{{ $gradeRow['male'] }}</td>
                                                    <td class="enrolment-summary__num">{{ $gradeRow['female'] }}</td>
                                                    <td class="enrolment-summary__num enrolment-summary__num--bold">{{ $gradeRow['total'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="enrolment-summary__subtotal">
                                                <td class="text-right font-black italic">Total</td>
                                                <td class="enrolment-summary__num">{{ $stageMale }}</td>
                                                <td class="enrolment-summary__num">{{ $stageFemale }}</td>
                                                <td class="enrolment-summary__num enrolment-summary__num--bold">{{ $stageTotal }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                {{-- ELEM GRAND TOTAL row after Elementary --}}
                                @if($stageName === 'Elementary')
                                    <div class="enrolment-summary__grand-row enrolment-summary__grand-row--elem">
                                        <span class="enrolment-summary__grand-label">ELEM GRAND TOTAL</span>
                                        <span class="enrolment-summary__grand-num">{{ $elemGrandMale }}</span>
                                        <span class="enrolment-summary__grand-num">{{ $elemGrandFemale }}</span>
                                        <span class="enrolment-summary__grand-num enrolment-summary__grand-num--bold">{{ $elemGrandTotal }}</span>
                                    </div>
                                @endif

                                {{-- JHS GRAND TOTAL row after Junior High School --}}
                                @if($stageName === 'Junior High School')
                                    @php
                                        $jhsMale = $stageMale;
                                        $jhsFemale = $stageFemale;
                                        $jhsTotal = $stageTotal;
                                    @endphp
                                    <div class="enrolment-summary__grand-row enrolment-summary__grand-row--jhs">
                                        <span class="enrolment-summary__grand-label">JHS GRAND TOTAL</span>
                                        <span class="enrolment-summary__grand-num">{{ $jhsMale }}</span>
                                        <span class="enrolment-summary__grand-num">{{ $jhsFemale }}</span>
                                        <span class="enrolment-summary__grand-num enrolment-summary__grand-num--bold">{{ $jhsTotal }}</span>
                                    </div>
                                @endif
                            @endforeach

                            {{-- GRAND TOTAL --}}
                            <div class="enrolment-summary__grand-row enrolment-summary__grand-row--final">
                                <span class="enrolment-summary__grand-label">GRAND TOTAL</span>
                                <span class="enrolment-summary__grand-num">{{ $grandMale }}</span>
                                <span class="enrolment-summary__grand-num">{{ $grandFemale }}</span>
                                <span class="enrolment-summary__grand-num enrolment-summary__grand-num--bold">{{ $grandTotal }}</span>
                            </div>
                        </div>
                    </section>
                </div>

                {{-- ── Detail panel ─────────────── --}}
                <div x-show="tab === 'detail'" x-cloak role="tabpanel" aria-labelledby="tab-detail" class="space-y-4">
                    @include('reporting.partials.results-table')
                </div>
            </div>
        @else
            @isset($retentionSummary)
                @include('reporting.partials.retention-summary')
            @endisset
            @include('reporting.partials.results-table')
        @endif
    </div>
</x-layouts.app>
