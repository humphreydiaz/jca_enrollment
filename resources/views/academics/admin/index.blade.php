<x-layouts.app title="Academics · Class schedules">
    @php
        $canArchive = auth()->user()->can('archive', \App\Modules\Academics\Models\Section::class);
        $canDelete = auth()->user()->can('delete', \App\Modules\Academics\Models\Section::class);
        $yearQuery = $year ? ['year' => $year->public_id] : [];
    @endphp

    {{-- Old bookmarks such as /academics#school-years open the matching part of the Setup tab. --}}
    <script>
        (function () {
            var setupAnchors = ['#school-years', '#grade-levels', '#subjects', '#sections'];
            if (setupAnchors.indexOf(window.location.hash) !== -1) {
                window.location.replace(@json(route('academics.admin.setup', $yearQuery)) + window.location.hash);
            }
        })();
    </script>

    <div class="space-y-6">
        <x-academics.tabs active="schedules" :year="$year" />

        @if ($years->isEmpty())
            <x-ui.empty-state icon="academics" title="Start with the first school year"
                description="Create a school year, then load JCA's class program from the curriculum so every grade level, section, subject, and class time is filled in for you.">
                <x-slot:action>
                    <a href="{{ route('academics.admin.setup') }}#school-years" class="jca-button jca-button-primary">Create a school year</a>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <x-academics.year-filter
                :action="route('academics.admin.index')"
                :years="$years" :year="$year" :current-year="$currentYear"
                :grades="$grades" :grade="$grade"
                :sections="$sections" :section="$section"
                :show-archived="$showArchived" />

            {{-- ── School-year readiness ── --}}
            <section class="jca-card overflow-hidden" aria-labelledby="year-readiness-title">
                <div class="flex flex-wrap items-start justify-between gap-4 p-5 sm:p-6">
                    <div class="min-w-0">
                        <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-brand-orange-500">{{ $year->code }}{{ $currentYear?->is($year) ? ' · current school year' : '' }}</p>
                        <h2 id="year-readiness-title" class="mt-1 text-xl font-black text-brand-navy-900">{{ $year->name }}</h2>
                        <p class="mt-1 text-sm text-muted">
                            {{ $year->starts_on->format('M j, Y') }} – {{ $year->ends_on->format('M j, Y') }}
                            @if ($year->enrollment_opens_on)
                                · Enrollment {{ $year->enrollment_opens_on->format('M j') }}{{ $year->enrollment_closes_on ? ' – '.$year->enrollment_closes_on->format('M j') : '' }}
                            @endif
                        </p>
                    </div>
                    <x-ui.status-badge :status="$year->status" />
                </div>

                <div class="border-t border-border bg-canvas/60 px-5 py-4 sm:px-6">
                    @if ($publishedCurriculum)
                        <p class="flex flex-wrap items-center gap-2 text-sm">
                            <x-ui.status-badge status="published" label="Class program published" />
                            <span class="text-muted">{{ $publishedCurriculum->name }} · version {{ $publishedCurriculum->version }}</span>
                            <a href="{{ route('academics.admin.curricula.index', ['curriculum' => $publishedCurriculum->public_id]) }}" class="font-bold text-brand-navy-700 hover:underline">View curriculum</a>
                        </p>
                    @elseif ($draftCurriculum)
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm"><x-ui.status-badge status="draft" label="Class program draft" /> <span class="ml-1 text-muted">“{{ $draftCurriculum->name }}” is waiting for review. Publishing it creates this year's sections and subjects.</span></p>
                            <a href="{{ route('academics.admin.curricula.index', ['curriculum' => $draftCurriculum->public_id]) }}" class="jca-button jca-button-primary">Review and publish</a>
                        </div>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm text-ink"><strong>No class program yet.</strong> <span class="text-muted">Load JCA's default class program to fill in every grade level, section, subject, and class time at once.</span></p>
                            @can('create', \App\Modules\Academics\Models\Curriculum::class)
                                <a href="{{ route('academics.admin.curricula.index', $yearQuery) }}#new-curriculum" class="jca-button jca-button-primary">Load class program</a>
                            @endcan
                        </div>
                    @endif
                </div>

                <dl class="grid grid-cols-2 border-t border-border sm:grid-cols-4">
                    @php
                        $steps = [
                            ['label' => 'Active sections', 'value' => $checklist['sections'], 'total' => null],
                            ['label' => 'Advisers assigned', 'value' => $checklist['advisers'], 'total' => $checklist['sections']],
                            ['label' => 'Subject offerings', 'value' => $checklist['offerings'], 'total' => null],
                            ['label' => 'Subject teachers', 'value' => $checklist['teachers'], 'total' => $checklist['offerings']],
                        ];
                    @endphp
                    @foreach ($steps as $step)
                        @php $complete = $step['total'] === null ? $step['value'] > 0 : ($step['total'] > 0 && $step['value'] >= $step['total']); @endphp
                        <div class="border-border px-5 py-3 sm:px-6 [&:not(:last-child)]:border-r">
                            <dt class="text-xs font-bold text-muted">{{ $step['label'] }}</dt>
                            <dd class="mt-1 flex items-center gap-2 text-lg font-black {{ $complete ? 'text-success-700' : 'text-warning-800' }}">
                                <x-ui.icon :name="$complete ? 'check' : 'warning'" size="size-4" />
                                {{ $step['value'] }}{{ $step['total'] !== null ? ' / '.$step['total'] : '' }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            @if ($section)
                @include('academics.admin.partials.section-detail')
            @else
                @include('academics.admin.partials.section-list')
            @endif
        @endif
    </div>
</x-layouts.app>
