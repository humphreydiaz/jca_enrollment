
<x-layouts.app title="Financial records">
<style>
@media (min-width: 768px) {
    .financial-filter-row {
        display: grid !important;
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) auto auto !important;
        align-items: end !important;
        gap: 12px !important;
    }

    .financial-filter-row > * {
        min-width: 0;
    }

    .financial-filter-row .jca-input {
        width: 100%;
    }
}
</style>
    @php
        $records = collect($assessments)->values();

        $schoolYears = $records
            ->map(fn ($record) => $record->enrollment?->academicYear)
            ->filter()
            ->unique('id')
            ->sortByDesc('name')
            ->values();

        $gradeLevels = $records
            ->map(fn ($record) => $record->enrollment?->gradeLevel)
            ->filter()
            ->unique('id')
            ->sortBy('id')
            ->values();

        $totalRecords = $records->count();
    @endphp

    <div
        class="space-y-6"
        x-data="{
            search: '',
            schoolYear: '',
            gradeLevel: '',
            appliedSearch: '',
            appliedYear: '',
            appliedGrade: '',
            currentPage: 1,
            perPage: 25,
            records: @js(
                $records->map(function ($record) {
                    $enrollment = $record->enrollment;

                    return [
                        'id' => $record->id,
                        'search' => mb_strtolower(
                            ($enrollment?->student?->full_name ?? '') . ' ' .
                            ($record->assessment_reference ?? '')
                        ),
                        'year' => (string) ($enrollment?->academicYear?->id ?? ''),
                        'grade' => (string) ($enrollment?->gradeLevel?->id ?? ''),
                    ];
                })->values()->all()
            ),

            get filteredRecords() {
                return this.records.filter(record => {
                    return (
                        record.search.includes(this.appliedSearch) &&
                        (!this.appliedYear || record.year === this.appliedYear) &&
                        (!this.appliedGrade || record.grade === this.appliedGrade)
                    );
                });
            },

            get filteredCount() {
                return this.filteredRecords.length;
            },

            get totalPages() {
                return Math.max(
                    1,
                    Math.ceil(this.filteredCount / this.perPage)
                );
            },

            get firstItem() {
                if (this.filteredCount === 0) return 0;

                return (this.currentPage - 1) * this.perPage + 1;
            },

            get lastItem() {
                return Math.min(
                    this.currentPage * this.perPage,
                    this.filteredCount
                );
            },

            applyFilters() {
                this.appliedSearch = this.search.toLowerCase().trim();
                this.appliedYear = this.schoolYear;
                this.appliedGrade = this.gradeLevel;
                this.currentPage = 1;
            },

            clearFilters() {
                this.search = '';
                this.schoolYear = '';
                this.gradeLevel = '';
                this.applyFilters();
            },

            visible(recordId) {
                const index = this.filteredRecords.findIndex(
                    record => String(record.id) === String(recordId)
                );

                if (index === -1) return false;

                return (
                    index >= (this.currentPage - 1) * this.perPage &&
                    index < this.currentPage * this.perPage
                );
            },

            changePage(page) {
                if (page < 1 || page > this.totalPages) return;

                this.currentPage = page;
            }
        }"
    >

        {{-- PAGE HEADER --}}
        <x-ui.page-header
            title="Financial records"
            description="Search, filter, and open student financial records. Academic enrollment and financial standing are shown separately."
            eyebrow="Finance workspace"
        />

        {{-- FILTER BAR --}}
        <section
            class="jca-card overflow-hidden"
            aria-label="Filter financial records"
        >
            <form
                @submit.prevent="applyFilters()"
                class="financial-filter-row grid gap-3 p-4 sm:p-5 xl:grid-cols-[minmax(14rem,2fr)_repeat(2,minmax(8rem,1fr))_auto_auto] lg:items-end"
            >

                {{-- SEARCH --}}
                <label class="jca-label min-w-0">
                    Search students

                    <input
                        type="search"
                        x-model="search"
                        placeholder="Name or assessment reference..."
                        class="jca-input w-full"
                    >
                </label>

                {{-- SCHOOL YEAR --}}
                <label class="jca-label min-w-0">
                    School year

                    <select
                        x-model="schoolYear"
                        class="jca-input w-full"
                    >
                        <option value="">All years</option>

                        @foreach ($schoolYears as $year)
                            <option value="{{ $year->id }}">
                                {{ $year->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                {{-- GRADE LEVEL --}}
                <label class="jca-label min-w-0">
                    Grade level

                    <select
                        x-model="gradeLevel"
                        class="jca-input w-full"
                    >
                        <option value="">All grades</option>

                        @foreach ($gradeLevels as $grade)
                            <option value="{{ $grade->id }}">
                                {{ $grade->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                {{-- FILTER BUTTON --}}
                <div class="flex items-end">
                    <x-ui.button
                        type="submit"
                        variant="primary"
                        class="w-full justify-center whitespace-nowrap lg:min-w-[100px]"
                    >
                        <x-ui.icon name="search" size="size-4" />
                        Filter
                    </x-ui.button>
                </div>

                {{-- CLEAR BUTTON --}}
                <div class="flex items-end">
                    <button
                        type="button"
                        @click="clearFilters()"
                        class="jca-button jca-button-secondary w-full justify-center whitespace-nowrap"
                    >
                        Clear
                    </button>
                </div>

            </form>
        </section>

        {{-- RECORDS TABLE --}}
        <section
            class="jca-card overflow-hidden"
            aria-label="Student financial records"
        >

            {{-- RECORD COUNT --}}
            <div
                class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-5 py-4 sm:px-6"
            >
                <p class="text-sm text-muted">
                    <span
                        class="font-bold text-ink"
                        x-text="filteredCount"
                    >{{ $totalRecords }}</span>

                    financial records found

                    <span x-show="filteredCount > 0">
                        &middot; Showing
                        <span x-text="firstItem"></span>&ndash;<span x-text="lastItem"></span>
                    </span>
                </p>

                <p class="text-xs text-muted">
                    Select a record to view financial details.
                </p>
            </div>

            {{-- TABLE --}}
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">

                    <thead
                        class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"
                    >
                        <tr>
                            <th class="px-5 py-3">
                                Student
                            </th>

                            <th class="px-5 py-3">
                                Grade level
                            </th>

                            <th class="px-5 py-3">
                                School year
                            </th>

                            <th class="px-5 py-3">
                                Assessment reference
                            </th>

                            <th class="px-5 py-3 text-right">
                                Record
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-border bg-white">

                        @foreach ($records as $assessment)
                            @php
                                $enrollment = $assessment->enrollment;

                                $student = $enrollment?->student;

                                $studentName = $student?->full_name
                                    ?? 'Unknown student';

                                $grade = $enrollment?->gradeLevel;

                                $year = $enrollment?->academicYear;

                                $firstInitial = mb_strtoupper(
                                    mb_substr($student?->first_name ?: 'S', 0, 1)
                                );

                                $lastInitial = mb_strtoupper(
                                    mb_substr($student?->last_name ?: '', 0, 1)
                                );
                            @endphp

                            <tr
                                x-show="visible(@js($assessment->id))"
                                class="transition-colors hover:bg-slate-50/60"
                            >

                                {{-- STUDENT --}}
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">

                                        <span
                                            class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-blue-100 text-sm font-black text-brand-navy-700"
                                        >
                                            {{ $firstInitial }}{{ $lastInitial }}
                                        </span>

                                        <div class="min-w-0">
                                            <p
                                                class="font-bold text-brand-navy-900"
                                            >
                                                {{ $studentName }}
                                            </p>

                                            <p class="mt-0.5 text-xs text-muted">
                                                Financial assessment
                                            </p>
                                        </div>

                                    </div>
                                </td>

                                {{-- GRADE --}}
                                <td class="whitespace-nowrap px-5 py-4">
                                    {{ $grade?->name ?? 'N/A' }}
                                </td>

                                {{-- SCHOOL YEAR --}}
                                <td class="whitespace-nowrap px-5 py-4 text-muted">
                                    {{ $year?->name ?? 'N/A' }}
                                </td>

                                {{-- ASSESSMENT --}}
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-ink">
                                        {{ $assessment->assessment_reference }}
                                    </p>

                                    <p class="mt-1 text-xs text-muted">
                                        Revision {{ $assessment->revision_number }}
                                    </p>
                                </td>

                                {{-- VIEW RECORD --}}
                                <td class="px-5 py-4 text-right">
                                    <a
                                        href="{{ route('billing.portal.show', $assessment) }}"
                                        class="jca-button-secondary inline-flex items-center gap-2 whitespace-nowrap"
                                    >
                                        View record

                                        <x-ui.icon
                                            name="arrow-right"
                                            size="size-4"
                                        />
                                    </a>
                                </td>

                            </tr>
                        @endforeach

                    </tbody>
                </table>
            </div>

            {{-- EMPTY STATE --}}
            <div
                x-show="filteredCount === 0"
                x-cloak
                class="px-5 py-10 text-center"
            >
                <p class="font-bold text-brand-navy-900">
                    No matching financial records found.
                </p>

                <p class="mt-1 text-sm text-muted">
                    Adjust your search or clear the filters.
                </p>
            </div>

            {{-- PAGINATION --}}
            <div
                x-show="totalPages > 1"
                x-cloak
                class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-4 sm:px-6"
            >
                <p class="text-sm text-muted">
                    Page
                    <span x-text="currentPage"></span>
                    of
                    <span x-text="totalPages"></span>
                </p>

                <div class="flex items-center gap-2">

                    <button
                        type="button"
                        @click="changePage(currentPage - 1)"
                        :disabled="currentPage === 1"
                        class="jca-button jca-button-secondary disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Previous
                    </button>

                    <button
                        type="button"
                        @click="changePage(currentPage + 1)"
                        :disabled="currentPage === totalPages"
                        class="jca-button jca-button-secondary disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Next
                    </button>

                </div>
            </div>

        </section>

    </div>
</x-layouts.app>
