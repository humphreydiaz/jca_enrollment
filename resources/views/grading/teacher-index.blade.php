<x-layouts.app title="Grades">
    @php
        $stages = [
            'approved' => ['label' => 'approved', 'bar' => 'bg-success-700', 'dot' => 'bg-success-700'],
            'submitted' => ['label' => 'waiting for Registrar approval', 'bar' => 'bg-brand-navy-700', 'dot' => 'bg-brand-navy-700'],
            'ready' => ['label' => 'ready to submit', 'bar' => 'bg-brand-gold-500', 'dot' => 'bg-brand-gold-500'],
            'in_progress' => ['label' => 'in progress', 'bar' => 'bg-brand-orange-500/60', 'dot' => 'bg-brand-orange-500/60'],
            'not_started' => ['label' => 'not started', 'bar' => 'bg-border', 'dot' => 'bg-border'],
        ];
    @endphp
    <div class="space-y-6">
        <x-ui.page-header
            eyebrow="Teacher workspace"
            title="Grades"
            description="Enter Q1–Q4 for each student. The final grade is calculated when all four quarters are in; submit it for the Registrar to approve.">
            <x-slot:actions>
                <a href="{{ route('academics.teacher.assignments', $year ? ['year' => $year->public_id] : []) }}" class="jca-button-secondary inline-flex items-center gap-2">
                    <x-ui.icon name="teaching" size="size-4" /> My teaching
                </a>
            </x-slot:actions>
        </x-ui.page-header>

        <x-teaching.year-tabs :years="$years" :year="$year" route="grading.teacher.index" />

        @if ($sections->isEmpty())
            <div class="jca-card p-6">
                <x-ui.empty-state icon="academics" title="No subject classes to grade" description="Grades 1–10 subject classes you are assigned to appear here. Ask the Admin if a class is missing." />
            </div>
        @else
            <dl class="grid grid-cols-2 gap-2 sm:gap-3 xl:grid-cols-4">
                @foreach ([
                    ['Subject classes', $totals['classes'], 'text-brand-navy-900'],
                    ['Grades to finish', $totals['toFinish'], $totals['toFinish'] ? 'text-warning-800' : 'text-brand-navy-900'],
                    ['Ready to submit', $totals['toSubmit'], $totals['toSubmit'] ? 'text-brand-navy-700' : 'text-brand-navy-900'],
                    ['Waiting for approval', $totals['waiting'], 'text-brand-navy-900'],
                ] as [$label, $value, $tone])
                    <div class="jca-card p-3 sm:p-4">
                        <dt class="text-xs font-extrabold uppercase tracking-wider text-muted sm:text-xs">{{ $label }}</dt>
                        <dd class="mt-1 text-xl font-black tabular-nums sm:text-2xl {{ $tone }}">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @foreach ($sections as $entry)
                @php($section = $entry['section'])
                <section class="jca-card overflow-hidden" aria-labelledby="grades-section-{{ $section->public_id }}">
                    <header class="flex flex-wrap items-baseline justify-between gap-2 border-b border-border px-5 py-4 sm:px-6">
                        <h2 id="grades-section-{{ $section->public_id }}" class="text-lg font-black text-brand-navy-900">{{ $section->gradeLevel->name }} · {{ $section->name }}</h2>
                        <p class="text-sm font-bold text-muted">{{ $entry['studentCount'] }} {{ Str::plural('student', $entry['studentCount']) }}</p>
                    </header>
                    <ul class="divide-y divide-border">
                        @foreach ($entry['classes'] as $class)
                            @php($p = $class['progress'])
                            @php($subject = $class['assignment']->sectionSubject->subject->name)
                            @php($done = $p['total'] > 0 && $p['approved'] === $p['total'])
                            <li class="grid gap-3 px-5 py-4 sm:grid-cols-[minmax(10rem,14rem)_minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:px-6">
                                <div>
                                    <p class="font-black text-brand-navy-900">{{ $subject }}</p>
                                    <p class="text-xs font-semibold {{ $done ? 'text-success-700' : 'text-muted' }}">
                                        @if ($p['total'] === 0)
                                            No students yet
                                        @elseif ($done)
                                            All final grades approved
                                        @else
                                            {{ $p['approved'] }} of {{ $p['total'] }} approved
                                        @endif
                                    </p>
                                </div>
                                <div class="min-w-0">
                                    @if ($p['total'] > 0)
                                        <div class="flex h-2.5 overflow-hidden rounded-full bg-canvas" role="img" aria-label="{{ collect($stages)->map(fn ($stage, $key) => $p[$key] ? $p[$key].' '.$stage['label'] : null)->filter()->join(', ') }}">
                                            @foreach ($stages as $key => $stage)
                                                @if ($p[$key])
                                                    <span class="{{ $stage['bar'] }}" style="width: {{ round($p[$key] / $p['total'] * 100, 2) }}%"></span>
                                                @endif
                                            @endforeach
                                        </div>
                                        <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" aria-hidden="true">
                                            @foreach ($stages as $key => $stage)
                                                @if ($p[$key])
                                                    <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full {{ $stage['dot'] }}"></span><strong class="tabular-nums text-ink">{{ $p[$key] }}</strong> {{ $stage['label'] }}</span>
                                                @endif
                                            @endforeach
                                        </p>
                                    @endif
                                </div>
                                <a href="{{ route('grading.teacher.show', $class['assignment']) }}" class="{{ $done ? 'jca-button-secondary' : 'jca-button-primary' }} justify-self-start whitespace-nowrap sm:justify-self-end">
                                    {{ $done ? 'View grades' : 'Enter grades' }}<span class="sr-only"> for {{ $subject }}, {{ $section->gradeLevel->name }} {{ $section->name }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        @endif
    </div>
</x-layouts.app>
