<x-layouts.app title="Grade approval">



    @php



        $base = collect($query)->except(['tab', 'page'])->all();



        $tabUrl = fn (string $key) => route('grading.admin.index', [...$base, 'tab' => $key]);



        $classUrl = fn (array $class) => route('grading.admin.class', [$class['section'], $class['subject']]);



        $classTitle = fn (array $class) => $class['grade']->name.' · '.$class['section']->name.' · '.$class['subject']->name;



        $hasFilters = collect($base)->except('year')->isNotEmpty();



        $cellTone = [



            'approved' => 'border-success-700/30 bg-success-50 text-success-700',



            'waiting' => 'border-brand-navy-700 bg-brand-navy-700 text-white',



            'in_progress' => 'border-brand-orange-500/50 bg-warning-50 text-warning-800',



            'not_started' => 'border-border bg-canvas text-muted',



        ];



        $cellLabel = ['approved' => 'all approved', 'waiting' => 'waiting for approval', 'in_progress' => 'teacher still entering', 'not_started' => 'nothing entered yet'];



    @endphp







    <div class="space-y-6" x-data="gradeApproval(@js($reviewedKeys), @js($preselect))">



        <x-ui.page-header



            eyebrow="Registrar review"



            title="Grade approval"



            description="Track which classes have submitted final grades, review each class, and approve. Approved grades are released to families and count toward promotion." />







        {{-- Filters (plain GET form; Alpine only auto-submits) --}}



        <form method="GET" action="{{ route('grading.admin.index') }}" class="jca-card p-4"



              x-data="{ open: window.innerWidth >= 768 }"



              x-on:change="if ($event.target.tagName === 'SELECT' || $event.target.type === 'checkbox') { if ($event.target.name === 'year' || $event.target.name === 'grade') { $el.querySelector('[name=section]').value = '' } $el.requestSubmit() }">



            <button type="button" class="flex w-full items-center justify-between text-left md:hidden" @click="open = !open" :aria-expanded="open.toString()" x-cloak>



                <span class="text-sm font-black text-brand-navy-900">Filters <span class="font-semibold text-muted">· {{ $year?->code }}{{ $hasFilters ? ' · '.collect($base)->except('year')->count().' more' : '' }}</span></span>



                <span class="text-sm font-bold text-brand-navy-700" x-text="open ? 'Hide' : 'Show'"></span>



            </button>



            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-[repeat(5,minmax(0,1fr))_minmax(12rem,1.4fr)]" x-show="open" :class="open ? 'mt-3 md:mt-0' : ''">



            <input type="hidden" name="tab" value="{{ $tab }}">



            @foreach ([



                ['year', 'School year', $years, $year, fn ($o) => $o->code, null],



                ['grade', 'Grade level', $grades, $grade, fn ($o) => $o->name, 'All grades'],



                ['section', 'Section', $sections->when($grade, fn ($c) => $c->where('grade_level_id', $grade->id)), $section, fn ($o) => $o->gradeLevel->name.' · '.$o->name, 'All sections'],



                ['subject', 'Subject', $subjects, $subject, fn ($o) => $o->name, 'All subjects'],



                ['teacher', 'Teacher', $teachers, $teacher, fn ($o) => $o->name, 'All teachers'],



            ] as [$name, $label, $options, $selected, $text, $placeholder])



                <div class="space-y-1.5">



                    <label for="filter_{{ $name }}" class="block text-xs font-extrabold uppercase tracking-wider text-muted">{{ $label }}</label>



                    <select id="filter_{{ $name }}" name="{{ $name }}" class="jca-input">



                        @if ($placeholder)<option value="">{{ $placeholder }}</option>@endif



                        @foreach ($options as $option)



                            <option value="{{ $option->public_id }}" @selected($selected?->is($option))>{{ $text($option) }}</option>



                        @endforeach



                    </select>



                </div>



            @endforeach



            <div class="space-y-1.5 sm:col-span-2 lg:col-span-1">



                <label for="filter_search" class="block text-xs font-extrabold uppercase tracking-wider text-muted">Student</label>



                <input id="filter_search" name="search" value="{{ $search }}" maxlength="100" placeholder="Name or Student ID" class="jca-input">



            </div>



            <div class="flex flex-wrap items-center justify-between gap-3 sm:col-span-2 lg:col-span-4 xl:col-span-6">



                <label class="jca-check text-sm">



                    <input type="hidden" name="flagged" value="0">



                    <input type="checkbox" name="flagged" value="1" @checked($flagged)>



                    Only classes with grades to check



                </label>



                <div class="flex items-center gap-3">



                    @if ($hasFilters)



                        <a href="{{ route('grading.admin.index', ['year' => $year?->public_id, 'tab' => $tab]) }}" class="text-sm font-bold text-brand-navy-700 hover:underline">Clear filters</a>



                    @endif



                    <x-ui.button type="submit" variant="secondary">Show</x-ui.button>



                </div>



            </div>



            </div>



        </form>







        @if ($totals['classes'] === 0)



            <div class="jca-card p-6">



                <x-ui.empty-state icon="academics" :title="$hasFilters ? 'No classes match these filters' : 'No Grades 1–10 classes with students yet'" description="Classes appear here once students are confirmed in a Grades 1–10 section with subjects." />



            </div>



        @else



            {{-- Deadline summary --}}



            <dl class="grid grid-cols-2 gap-3 xl:grid-cols-4">



                <div class="jca-card p-4">



                    <dt class="text-xs font-extrabold uppercase tracking-wider text-muted">Classes fully submitted</dt>



                    <dd class="mt-1 text-2xl font-black tabular-nums text-brand-navy-900">{{ $totals['fullySubmitted'] }} <span class="text-base font-bold text-muted">/ {{ $totals['classes'] }}</span></dd>



                    <dd class="mt-2 h-1.5 overflow-hidden rounded-full bg-canvas"><span class="block h-full bg-success-700" style="width: {{ $totals['classes'] ? round($totals['fullySubmitted'] / $totals['classes'] * 100, 1) : 0 }}%"></span></dd>



                </div>



                <div class="jca-card p-4">



                    <dt class="text-xs font-extrabold uppercase tracking-wider text-muted">Waiting for you</dt>



                    <dd class="mt-1 text-2xl font-black tabular-nums text-brand-navy-700">{{ number_format($totals['waitingGrades']) }}</dd>



                    <dd class="text-xs font-semibold text-muted">{{ Str::plural('grade', $totals['waitingGrades']) }} in {{ $totals['waitingClasses'] }} {{ Str::plural('class', $totals['waitingClasses']) }}</dd>



                </div>



                <div class="jca-card p-4">



                    <dt class="text-xs font-extrabold uppercase tracking-wider text-muted">Not submitted yet</dt>



                    <dd class="mt-1 text-2xl font-black tabular-nums {{ $totals['missingGrades'] ? 'text-warning-800' : 'text-brand-navy-900' }}">{{ number_format($totals['missingGrades']) }}</dd>



                    <dd class="text-xs font-semibold text-muted">{{ Str::plural('grade', $totals['missingGrades']) }} in {{ $totals['notSubmittedClasses'] }} {{ Str::plural('class', $totals['notSubmittedClasses']) }}</dd>



                </div>



                <div class="jca-card p-4">



                    <dt class="text-xs font-extrabold uppercase tracking-wider text-muted">Approved</dt>



                    <dd class="mt-1 text-2xl font-black tabular-nums text-success-700">{{ number_format($totals['approvedGrades']) }} <span class="text-base font-bold text-muted">/ {{ number_format($totals['grades']) }}</span></dd>



                    <dd class="text-xs font-semibold text-muted">final grades this year</dd>



                </div>



            </dl>







            {{-- Submission tracker: expandable grade levels with section and subject detail --}}

            @php

                $trackerGradeGroups = $classes->groupBy(fn (array $item) => $item['grade']->id);

                $trackerSubmitted = $classes->sum(fn (array $item) => $item['counts']['approved'] + $item['counts']['submitted']);

            @endphp

            <section class="jca-card overflow-hidden" aria-labelledby="submission-tracker-title">

                <header class="space-y-3 border-b border-border px-5 py-5 sm:px-6">

                    <div class="flex flex-wrap items-start justify-between gap-3">

                        <div>

                            <h2 id="submission-tracker-title" class="text-2xl font-black text-brand-navy-900">Submission tracker</h2>

                            <p class="mt-2 text-base leading-relaxed text-muted">Select a grade level to view its sections, subjects, and grade submission progress.</p>

                        </div>

                        <div class="rounded-xl bg-brand-blue-100 px-4 py-2 text-right">

                            <p class="text-sm font-bold uppercase tracking-wide text-brand-navy-700">Final grades submitted</p>

                            <p class="text-xl font-black tabular-nums text-brand-navy-900">{{ number_format($trackerSubmitted) }} / {{ number_format($totals['grades']) }}</p>

                        </div>

                    </div>

                    <div class="flex flex-wrap gap-x-6 gap-y-3 text-base font-semibold text-muted" aria-label="Submission status legend">

                        @foreach (['waiting' => 'Waiting for approval', 'in_progress' => 'Teacher entering', 'not_started' => 'Not started', 'approved' => 'All approved'] as $state => $label)

                            <span class="inline-flex items-center gap-2"><span class="size-4 rounded border {{ $cellTone[$state] }}" aria-hidden="true"></span>{{ $label }}</span>

                        @endforeach

                    </div>

                </header>



                <div class="divide-y divide-border">

                    @foreach ($trackerGradeGroups as $gradeClasses)

                        @php

                            $gradeFirst = $gradeClasses->first();

                            $gradeTotal = (int) $gradeClasses->sum('total');

                            $gradeSubmitted = (int) $gradeClasses->sum(fn (array $item) => $item['counts']['approved'] + $item['counts']['submitted']);

                            $gradeWaiting = (int) $gradeClasses->sum(fn (array $item) => $item['counts']['submitted']);

                            $gradePercent = $gradeTotal > 0 ? round($gradeSubmitted / $gradeTotal * 100) : 0;

                            $sectionGroups = $gradeClasses->groupBy(fn (array $item) => $item['section']->id);

                        @endphp

                        <details class="group/grade">

                            <summary class="cursor-pointer list-none px-5 py-5 transition hover:bg-brand-blue-100/40 sm:px-6 [&::-webkit-details-marker]:hidden">

                                <div class="flex flex-wrap items-center justify-between gap-x-5 gap-y-3">

                                    <div class="min-w-0 flex-1">

                                        <div class="flex items-center gap-3">

                                            <span class="text-lg font-black text-brand-navy-900 sm:text-xl">{{ $gradeFirst['grade']->name }}</span>

                                            <span class="rounded-full border border-border bg-canvas px-3 py-1.5 text-sm font-bold text-muted">{{ $sectionGroups->count() }} {{ Str::plural('section', $sectionGroups->count()) }}</span>

                                        </div>

                                        <p class="mt-2 text-base text-muted">{{ $gradeClasses->count() }} subject {{ Str::plural('class', $gradeClasses->count()) }} · {{ number_format($gradeWaiting) }} waiting for approval</p>

                                    </div>

                                    <div class="flex items-center gap-4">

                                        <div class="text-right">

                                            <p class="text-lg font-black tabular-nums text-brand-navy-900">{{ number_format($gradeSubmitted) }} / {{ number_format($gradeTotal) }}</p>

                                            <p class="text-sm font-semibold text-muted">final grades submitted</p>

                                        </div>

                                        <span class="text-xl font-bold text-brand-navy-700 transition-transform group-open/grade:rotate-180" aria-hidden="true">⌄</span>

                                    </div>

                                </div>

                                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-canvas" role="progressbar" aria-label="{{ $gradeFirst['grade']->name }} submission progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $gradePercent }}">

                                    <div class="h-full rounded-full {{ $gradePercent === 100 ? 'bg-success-700' : 'bg-brand-navy-700' }}" style="width: {{ $gradePercent }}%"></div>

                                </div>

                            </summary>



                            <div class="space-y-4 border-t border-border bg-canvas/40 px-4 py-4 sm:px-6">

                                @foreach ($sectionGroups as $sectionClasses)

                                    @php

                                        $sectionFirst = $sectionClasses->first();

                                        $sectionTotal = (int) $sectionClasses->sum('total');

                                        $sectionSubmitted = (int) $sectionClasses->sum(fn (array $item) => $item['counts']['approved'] + $item['counts']['submitted']);

                                    @endphp

                                    <div class="overflow-hidden rounded-xl border border-border bg-white">

                                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-brand-blue-100/40 px-4 py-3">

                                            <h3 class="text-lg font-black text-brand-navy-900">Section {{ $sectionFirst['section']->name }}</h3>

                                            <span class="text-base font-bold tabular-nums text-brand-navy-700">{{ $sectionSubmitted }} / {{ $sectionTotal }} grades submitted</span>

                                        </div>

                                        <ul class="divide-y divide-border">

                                            @foreach ($sectionClasses as $class)

                                                @php

                                                    $submittedSoFar = (int) ($class['counts']['approved'] + $class['counts']['submitted']);

                                                    $total = (int) $class['total'];

                                                    $percent = $total > 0 ? round($submittedSoFar / $total * 100) : 0;

                                                    $state = $class['state'];

                                                    $statusText = ['approved' => 'All approved', 'waiting' => 'Waiting for approval', 'in_progress' => 'Teacher entering', 'not_started' => 'Not started'][$state] ?? 'Not started';

                                                @endphp

                                                <li class="grid gap-3 px-4 py-4 md:grid-cols-[minmax(0,1.3fr)_minmax(10rem,1fr)_auto] md:items-center md:gap-5">

                                                    <div class="min-w-0">

                                                        <p class="text-lg font-extrabold text-brand-navy-900">{{ $class['subject']->name }}</p>

                                                        <p class="mt-1 text-base text-muted">{{ $class['teacherNames'] }}</p>

                                                        <span class="mt-2 inline-flex rounded-lg border px-3 py-1.5 text-sm font-bold {{ $cellTone[$state] ?? $cellTone['not_started'] }}">{{ $statusText }}</span>

                                                    </div>

                                                    <div>

                                                        <div class="mb-2 flex items-center justify-between gap-2 text-base">

                                                            <span class="font-semibold text-muted">Submitted</span>

                                                            <strong class="tabular-nums text-brand-navy-900">{{ $submittedSoFar }} / {{ $total }}</strong>

                                                        </div>

                                                        <div class="h-2.5 overflow-hidden rounded-full bg-canvas" role="progressbar" aria-label="{{ $classTitle($class) }} submission progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}">

                                                            <div class="h-full rounded-full {{ $state === 'approved' ? 'bg-success-700' : 'bg-brand-navy-700' }}" style="width: {{ $percent }}%"></div>

                                                        </div>

                                                        @if ($class['counts']['submitted'] > 0)

                                                            <p class="mt-1.5 text-sm font-bold text-brand-navy-700">{{ $class['counts']['submitted'] }} waiting for approval</p>

                                                        @endif

                                                    </div>

                                                    <a href="{{ $classUrl($class) }}"

                                                       @click="openClass($event, @js($class['key']), @js($classTitle($class)))"

                                                       class="jca-button-secondary inline-flex shrink-0 items-center justify-center whitespace-nowrap px-4 py-2.5 text-base"

                                                       aria-haspopup="dialog">View class <span aria-hidden="true">→</span><span class="sr-only"> {{ $classTitle($class) }}</span></a>

                                                </li>

                                            @endforeach

                                        </ul>

                                    </div>

                                @endforeach

                            </div>

                        </details>

                    @endforeach

                </div>

            </section>





            {{-- Work queues --}}



            <nav class="flex gap-1 overflow-x-auto rounded-xl border border-border bg-white p-1.5 shadow-sm" aria-label="Grade approval queues">



                @foreach ([



                    'waiting' => ['Waiting for approval', $totals['waitingClasses']],



                    'missing' => ['Not submitted yet', $totals['notSubmittedClasses']],



                    'reopen' => ['Reopen requests', $totals['reopen']],



                    'approved' => ['Approved', null],



                ] as $key => [$label, $count])



                    <a href="{{ $tabUrl($key) }}" @if ($tab === $key) aria-current="page" @endif



                       class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-lg px-4 py-2 text-sm font-bold {{ $tab === $key ? 'bg-brand-navy-700 text-white' : 'text-brand-navy-700 hover:bg-brand-blue-100' }}">



                        {{ $label }}



                        @if ($count !== null)



                            <span class="rounded-full px-2 py-0.5 text-xs tabular-nums {{ $tab === $key ? 'bg-white/20' : 'bg-brand-blue-100' }}">{{ $count }}</span>



                        @endif



                    </a>



                @endforeach



            </nav>







            @if ($tab === 'waiting')



                <section id="waiting-list" class="jca-card scroll-mt-28 overflow-hidden" aria-labelledby="waiting-title">



                    @if ($page->isEmpty())



                        <div class="p-6"><x-ui.empty-state icon="check" title="Nothing is waiting for approval" description="Classes appear here as soon as a teacher submits final grades." /></div>



                    @else



                        <form method="POST" action="{{ route('grading.admin.approve-classes') }}" x-ref="bulkForm" @submit="confirmBulk($event)">



                            @csrf



                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-3 sm:px-6">



                                <div>



                                    <h2 id="waiting-title" class="text-lg font-black text-brand-navy-900">Waiting for approval</h2>



                                    <p class="text-xs text-muted">Glance at each class's final grades below (⚑ marks one to check), then <strong>Review all</strong> and <strong>Approve selected</strong>. Open a class for its Q1–Q4 detail.</p>



                                </div>



                                <div class="flex flex-wrap items-center gap-3">



                                    <button type="submit" form="review-all-form" class="jca-button-secondary whitespace-nowrap">Review all on this page ({{ $page->count() }})</button>



                                    <label class="jca-check text-sm"><input type="checkbox" @change="toggleAllReviewed($event.target.checked)" :checked="selected.length > 0 && selected.length === reviewedOnPage().length" :disabled="!anyReviewed"> Select reviewed</label>



                                    <x-ui.button type="submit" x-bind:disabled="selected.length === 0">Approve selected <span class="tabular-nums" x-text="selected.length ? '(' + selected.length + ')' : ''"></span></x-ui.button>



                                </div>



                            </div>



                            <ul class="divide-y divide-border">



                                @foreach ($page as $class)



                                    @php($submittedSoFar = $class['counts']['approved'] + $class['counts']['submitted'])



                                    @php($serverReviewed = in_array($class['key'], $reviewedKeys, true))



                                    <li class="grid gap-3 px-5 py-4 sm:px-6 md:grid-cols-[1.75rem_minmax(0,1.6fr)_minmax(9rem,1fr)_7rem_7rem_auto] md:items-center"



                                        :class="selected.includes(@js($class['key'])) ? 'bg-brand-blue-100/40' : ''">



                                        <div class="flex items-start gap-3 md:contents">



                                        <div class="flex items-center pt-1 md:pt-0">



                                            <input type="checkbox" name="classes[]" value="{{ $class['key'] }}" x-model="selected"



                                                   class="size-4 rounded accent-brand-navy-700 disabled:opacity-30"



                                                   @if (! $serverReviewed) disabled @endif



                                                   @if (in_array($class['key'], $preselect, true)) checked @endif



                                                   :disabled="!isReviewed(@js($class['key']))"



                                                   aria-label="Select {{ $classTitle($class) }} for approval{{ $serverReviewed ? '' : ' (review the class first)' }}">



                                        </div>



                                        <div class="min-w-0">



                                            <p class="font-black text-brand-navy-900">{{ $classTitle($class) }}</p>



                                            <p class="text-xs text-muted">{{ $class['teacherNames'] }} · submitted {{ $class['oldestSubmittedAt']?->format('M j, g:i A') }}</p>



                                        </div>



                                        </div>



                                        <div>



                                            <x-grading.stages :counts="$class['counts']" :total="$class['total']" :legend="false" />



                                            <p class="mt-1 text-xs text-muted"><strong class="tabular-nums text-ink">{{ $class['counts']['submitted'] }}</strong> waiting · {{ $submittedSoFar }} of {{ $class['total'] }} submitted</p>



                                        </div>



                                        <div class="flex gap-8 md:contents">



                                        <p class="text-sm"><span class="block text-xs font-bold uppercase tracking-wider text-muted">Range</span><span class="font-black tabular-nums text-brand-navy-900">{{ $class['range'][0] }}–{{ $class['range'][1] }}</span></p>



                                        <p class="text-sm">



                                            <span class="block text-xs font-bold uppercase tracking-wider text-muted">Check</span>



                                            @if ($class['flagged'])



                                                <span class="rounded-full bg-warning-100 px-2 py-0.5 text-xs font-extrabold text-warning-800">⚑ {{ $class['flagged'] }}</span>



                                            @else



                                                <span class="text-muted">—</span>



                                            @endif



                                        </p>



                                        </div>



                                        <div class="flex items-center gap-2 md:justify-end">



                                            <span x-show="isReviewed(@js($class['key']))" @if (! $serverReviewed) style="display: none" @endif class="text-xs font-bold text-success-700">✓ Reviewed</span>



                                            <a href="{{ $classUrl($class) }}" @click="openClass($event, @js($class['key']), @js($classTitle($class)))" class="jca-button-secondary whitespace-nowrap" aria-haspopup="dialog">Open<span class="sr-only"> {{ $classTitle($class) }}</span></a>



                                        </div>



                                        {{-- At a glance: every waiting final grade in this class --}}



                                        <ul class="flex flex-wrap gap-1.5 md:col-span-5 md:col-start-2" aria-label="Waiting final grades in {{ $classTitle($class) }}">



                                            @foreach ($class['waiting'] as $row)



                                                @php($student = $row['student'])



                                                @php($g = $row['grade'])



                                                <li class="inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs {{ $row['flags'] ? 'border-warning-800/30 bg-warning-100 text-warning-800' : 'border-border bg-canvas text-ink' }}"



                                                    title="Q1 {{ $g->quarter_1 }} · Q2 {{ $g->quarter_2 }} · Q3 {{ $g->quarter_3 }} · Q4 {{ $g->quarter_4 }}{{ $row['flags'] ? ' — '.implode('; ', $row['flags']) : '' }}">



                                                    <span>{{ $student?->last_name }}, {{ mb_substr((string) $student?->first_name, 0, 1) }}.</span>



                                                    <strong class="tabular-nums">{{ $g->final_grade }}</strong>



                                                    @if ($row['flags'])<span aria-hidden="true">⚑</span><span class="sr-only">to check: {{ implode('; ', $row['flags']) }}</span>@endif



                                                </li>



                                            @endforeach



                                        </ul>



                                    </li>



                                @endforeach



                            </ul>



                        </form>



                        {{-- "Review all": exactly the grades shown above (D-045) --}}



                        <form method="POST" action="{{ route('grading.admin.review-classes') }}" id="review-all-form" class="hidden">



                            @csrf



                            @foreach ($page as $class)



                                @foreach ($class['waiting'] as $row)



                                    <input type="hidden" name="seen[{{ $class['key'] }}][]" value="{{ $row['grade']->public_id }}:{{ \App\Modules\Grading\Support\GradeFingerprint::of($row['grade']) }}">



                                @endforeach



                            @endforeach



                        </form>



                    @endif



                    @if ($page->hasPages())<div class="border-t border-border p-4">{{ $page->links() }}</div>@endif



                </section>



            @elseif ($tab === 'missing')



                <section class="space-y-4" aria-label="Classes not fully submitted">



                    @forelse ($page as $group)



                        <article class="jca-card overflow-hidden">



                            <header class="flex flex-wrap items-baseline justify-between gap-2 border-b border-border px-5 py-3 sm:px-6">



                                <h2 class="text-lg font-black text-brand-navy-900">{{ $group['teacher'] }}</h2>



                                <p class="text-sm font-bold text-warning-800">{{ $group['remaining'] }} {{ Str::plural('grade', $group['remaining']) }} not submitted · {{ $group['classes']->count() }} {{ Str::plural('class', $group['classes']->count()) }}</p>



                            </header>



                            <ul class="divide-y divide-border">



                                @foreach ($group['classes'] as $class)



                                    <li class="grid gap-2 px-5 py-3 sm:px-6 md:grid-cols-[minmax(0,1fr)_minmax(12rem,1.3fr)_auto] md:items-center md:gap-4">



                                        <p class="font-bold text-brand-navy-900">{{ $classTitle($class) }}<span class="block text-xs font-semibold text-muted">{{ $class['counts']['approved'] + $class['counts']['submitted'] }} of {{ $class['total'] }} submitted</span></p>



                                        <x-grading.stages :counts="$class['counts']" :total="$class['total']" />



                                        <a href="{{ $classUrl($class) }}" @click="openClass($event, @js($class['key']), @js($classTitle($class)))" class="justify-self-start text-sm font-bold text-brand-navy-700 hover:underline md:justify-self-end" aria-haspopup="dialog">View<span class="sr-only"> {{ $classTitle($class) }}</span> →</a>



                                    </li>



                                @endforeach



                            </ul>



                        </article>



                    @empty



                        <div class="jca-card p-6"><x-ui.empty-state icon="check" title="Every class has submitted" description="All current students have a submitted or approved final grade in every class." /></div>



                    @endforelse



                    @if ($page->hasPages())<div class="jca-card p-4">{{ $page->links() }}</div>@endif



                </section>



            @elseif ($tab === 'reopen')



                <section class="jca-card overflow-hidden" aria-labelledby="correction-title">



                    <h2 id="correction-title" class="border-b border-border px-5 py-4 text-lg font-black text-brand-navy-900 sm:px-6">Reopen requests from teachers</h2>



                    <div class="divide-y divide-border">



                        @forelse ($page as $correction)



                            <article class="p-5 sm:px-6">



                                <p class="font-bold text-brand-navy-900">{{ $correction->grade->enrollment->student->full_name }} · {{ $correction->grade->subject_name_snapshot }}</p>



                                <p class="mt-1 text-sm text-muted">{{ $correction->grade->enrollment->gradeLevel->name }}{{ $correction->grade->sectionSubject?->section ? ' · '.$correction->grade->sectionSubject->section->name : '' }} · {{ $correction->previous_status === 'finalized' ? 'Approved' : 'Submitted' }} result · Final {{ $correction->grade->final_grade }}</p>



                                <p class="mt-2 text-sm text-brand-navy-900">Teacher's reason: {{ $correction->reason }}</p>



                                <form method="POST" action="{{ route('grading.admin.corrections.review', $correction) }}" class="mt-4 max-w-2xl space-y-2">



                                    @csrf



                                    <input type="hidden" name="grade_correction" value="{{ $correction->public_id }}">



                                    <label class="block text-sm font-bold text-brand-navy-900" for="note-{{ $correction->public_id }}">Review note</label>



                                    <textarea id="note-{{ $correction->public_id }}" name="review_note" class="jca-input" rows="2" minlength="10" maxlength="1000" required>{{ old('grade_correction') === $correction->public_id ? old('review_note') : '' }}</textarea>



                                    @if (old('grade_correction') === $correction->public_id)



                                        @error('review_note')



                                            <p class="text-sm text-red-700">{{ $message }}</p>



                                        @enderror



                                    @endif



                                    <div class="flex flex-wrap gap-2">



                                        <button name="decision" value="approve" class="jca-button-primary" type="submit">Approve reopen</button>



                                        <button name="decision" value="reject" class="jca-button-secondary" type="submit">Reject request</button>



                                    </div>



                                </form>



                            </article>



                        @empty



                            <p class="p-6 text-sm text-muted">No reopen requests need review.</p>



                        @endforelse



                    </div>



                    @if ($page->hasPages())<div class="border-t border-border p-4">{{ $page->links() }}</div>@endif



                </section>



            @else



                <section class="jca-card overflow-hidden" aria-labelledby="approved-title">



                    <h2 id="approved-title" class="border-b border-border px-5 py-4 text-lg font-black text-brand-navy-900 sm:px-6">Recently approved</h2>



                    @if ($page->isEmpty())



                        <p class="p-6 text-sm text-muted">No final grades have been approved yet.</p>



                    @else



                        <div class="relative overflow-x-auto">



                            <table class="w-full min-w-[36rem] text-left text-sm">



                                <thead class="bg-canvas text-xs font-extrabold uppercase tracking-wider text-muted">



                                    <tr><th scope="col" class="px-5 py-2.5 sm:px-6">Student</th><th scope="col" class="px-3 py-2.5">Class</th><th scope="col" class="px-3 py-2.5 text-center">Final</th><th scope="col" class="px-5 py-2.5 sm:px-6">Approved</th></tr>



                                </thead>



                                <tbody class="divide-y divide-border">



                                    @foreach ($page as $row)



                                        <tr>



                                            <th scope="row" class="px-5 py-2.5 font-bold text-brand-navy-900 sm:px-6">{{ $row->enrollment->student->full_name }}</th>



                                            <td class="px-3 py-2.5">{{ $row->enrollment->gradeLevel->name }}{{ $row->sectionSubject?->section ? ' · '.$row->sectionSubject->section->name : '' }} · {{ $row->subject_name_snapshot }}</td>



                                            <td class="px-3 py-2.5 text-center font-black tabular-nums text-brand-navy-900">{{ $row->final_grade }}</td>



                                            <td class="whitespace-nowrap px-5 py-2.5 text-muted sm:px-6">{{ $row->finalized_at?->format('M j, Y g:i A') }}</td>



                                        </tr>



                                    @endforeach



                                </tbody>



                            </table>



                        </div>



                    @endif



                    @if ($page->hasPages())<div class="border-t border-border p-4">{{ $page->links() }}</div>@endif



                </section>



            @endif



        @endif







        {{-- Class review dialog --}}



        <dialog x-ref="classDialog" @close="closeClass()" @click.self="$refs.classDialog.close()"



                class="m-auto w-[min(56rem,calc(100%-2rem))] max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl border-0 bg-white p-0 text-ink shadow-2xl backdrop:bg-slate-950/55"



                aria-labelledby="class-dialog-title">



            <header class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-border bg-white px-5 py-4 sm:px-6">



                <div>



                    <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-brand-orange-500">Review class</p>



                    <h2 id="class-dialog-title" class="mt-1 text-xl font-black text-brand-navy-900" x-text="classTitle"></h2>



                </div>



                <button type="button" class="grid size-10 place-items-center rounded-lg text-muted hover:bg-slate-100" @click="$refs.classDialog.close()" aria-label="Close class review"><x-ui.icon name="close" /></button>



            </header>



            <div class="p-5 sm:p-6" aria-live="polite">



                <p x-show="classLoading" class="py-10 text-center text-sm font-semibold text-muted">Loading…</p>



                <p x-show="classError" class="rounded-xl bg-warning-50 p-4 text-sm font-semibold text-warning-800" x-text="classError"></p>



                <div x-show="!classLoading && !classError" x-html="classHtml"></div>



            </div>



        </dialog>



    </div>



</x-layouts.app>