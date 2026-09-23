<x-layouts.app :title="$definition['label'].' report'">
    <div class="mx-auto max-w-screen-2xl space-y-6">
        <header><p class="text-sm font-bold text-muted">Operational report</p><h1 class="mt-1 text-3xl font-black text-brand-navy-900">{{ $definition['label'] }}</h1><p class="mt-2 text-muted">{{ $definition['description'] }}</p></header>
        <form class="jca-card grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4" method="GET" action="{{ route('reporting.show', $reportCode) }}">
            @if(in_array('school_year', $allowedFilters, true))<label class="text-sm font-bold">School year<select class="mt-1 w-full rounded-lg border-border" name="school_year"><option value="">All school years</option>@foreach($academicYears as $year)<option value="{{ $year->public_id }}" @selected(($filters['school_year'] ?? '') === $year->public_id)>{{ $year->name }}</option>@endforeach</select></label>@endif
            @if(in_array('grade', $allowedFilters, true))<label class="text-sm font-bold">Grade<select class="mt-1 w-full rounded-lg border-border" name="grade"><option value="">All grades</option>@foreach($gradeLevels as $grade)<option value="{{ $grade->public_id }}" @selected(($filters['grade'] ?? '') === $grade->public_id)>{{ $grade->name }}</option>@endforeach</select></label>@endif
            @if(in_array('section', $allowedFilters, true))<label class="text-sm font-bold">Section<select class="mt-1 w-full rounded-lg border-border" name="section"><option value="">All sections</option>@foreach($sections as $section)<option value="{{ $section->public_id }}" @selected(($filters['section'] ?? '') === $section->public_id)>{{ $section->name }} — {{ $section->gradeLevel->name }}</option>@endforeach</select></label>@endif
            @if(in_array('status', $allowedFilters, true))<label class="text-sm font-bold">Status<input class="mt-1 w-full rounded-lg border-border" name="status" value="{{ $filters['status'] ?? '' }}" maxlength="40" placeholder="Exact status"></label>@endif
            @if(in_array('date_from', $allowedFilters, true))<label class="text-sm font-bold">From date<input class="mt-1 w-full rounded-lg border-border" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>@endif
            @if(in_array('date_to', $allowedFilters, true))<label class="text-sm font-bold">To date<input class="mt-1 w-full rounded-lg border-border" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>@endif
            <div class="flex items-end gap-2"><x-ui.button type="submit">Apply filters</x-ui.button><a class="jca-button jca-button-secondary" href="{{ route('reporting.show', $reportCode) }}">Clear</a></div>
        </form>
        <div class="flex flex-wrap gap-3"><a class="jca-button jca-button-secondary" href="{{ route('reporting.print', [$reportCode] + request()->except('page')) }}" target="_blank" rel="noopener">Print view</a><form method="POST" action="{{ route('reporting.exports.store', [$reportCode] + request()->except('page')) }}">@csrf<x-ui.button type="submit">Queue private CSV</x-ui.button></form></div>

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

                {{-- ── Detail panel (existing table) ─────────────── --}}
                <div x-show="tab === 'detail'" x-cloak role="tabpanel" aria-labelledby="tab-detail" class="space-y-4">
                    <section class="jca-card overflow-hidden" aria-label="Report results"><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-border bg-brand-blue-100">@foreach($columns as $label)<th scope="col" class="whitespace-nowrap p-3 font-black text-brand-navy-900">{{ $label }}</th>@endforeach</tr></thead><tbody>
                        @forelse($rows as $row)<tr class="border-b border-border">@foreach($columns as $field => $label)<td class="whitespace-nowrap p-3 {{ in_array($field, ['gross_total','discount_total','net_total','amount','required_amount','satisfied_amount'], true) ? 'text-right tabular-nums' : '' }}">@if($field === 'status' && filled(data_get($row, $field)))<x-ui.status-badge :status="data_get($row, $field)" />@else{{ data_get($row, $field) ?? '—' }}@endif</td>@endforeach</tr>@empty<tr><td class="p-6 text-muted" colspan="{{ count($columns) }}">No records match the authorized filters.</td></tr>@endforelse
                    </tbody></table></div></section>{{ $rows->links() }}
                </div>
            </div>
        @else
            {{-- ── Standard report table (non-enrollment reports) ── --}}
            <section class="jca-card overflow-hidden" aria-label="Report results"><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-border bg-brand-blue-100">@foreach($columns as $label)<th scope="col" class="whitespace-nowrap p-3 font-black text-brand-navy-900">{{ $label }}</th>@endforeach</tr></thead><tbody>
                @forelse($rows as $row)<tr class="border-b border-border">@foreach($columns as $field => $label)<td class="whitespace-nowrap p-3 {{ in_array($field, ['gross_total','discount_total','net_total','amount','required_amount','satisfied_amount'], true) ? 'text-right tabular-nums' : '' }}">@if($field === 'status' && filled(data_get($row, $field)))<x-ui.status-badge :status="data_get($row, $field)" />@else{{ data_get($row, $field) ?? '—' }}@endif</td>@endforeach</tr>@empty<tr><td class="p-6 text-muted" colspan="{{ count($columns) }}">No records match the authorized filters.</td></tr>@endforelse
            </tbody></table></div></section>{{ $rows->links() }}
        @endif
    </div>
</x-layouts.app>
