{{-- Year (or grade) overview: one row per section, each opening its class schedule. --}}
<section id="section-details" class="jca-card scroll-mt-28 overflow-hidden" aria-labelledby="section-list-title">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4 sm:px-6">
        <div>
            <h2 id="section-list-title" class="text-lg font-black text-brand-navy-900">{{ $grade ? $grade->name.' sections' : 'Sections in '.$year->code }}</h2>
            <p class="text-xs text-muted">Open a section to see its subjects, class times, adviser, and subject teachers.</p>
        </div>
    </div>

    @if ($visibleSections->isEmpty())
        <div class="p-5 sm:p-6">
            <x-ui.empty-state icon="sections"
                :title="$grade ? 'No sections for '.$grade->name.' yet' : 'No sections for this school year yet'"
                description="Publishing the class program creates the sections. You can also add a section by hand below." />
        </div>
    @else
        <div class="relative overflow-x-auto">
            <table class="w-full min-w-[46rem] text-left text-sm">
                <thead class="bg-canvas text-xs font-extrabold uppercase tracking-wider text-muted">
                    <tr>
                        <th scope="col" class="px-5 py-3 sm:px-6">Section</th>
                        <th scope="col" class="px-3 py-3">Adviser</th>
                        <th scope="col" class="px-3 py-3">Subjects</th>
                        <th scope="col" class="px-3 py-3">Teachers</th>
                        <th scope="col" class="px-3 py-3">Enrolled</th>
                        <th scope="col" class="px-3 py-3">Status</th>
                        <th scope="col" class="px-5 py-3 text-right sm:px-6"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                @foreach ($visibleSections->groupBy(fn ($item) => $item->gradeLevel->name) as $gradeName => $gradeSections)
                    <tbody class="border-t border-border">
                        <tr class="bg-brand-blue-100/50">
                            <th scope="colgroup" colspan="7" class="px-5 py-2 text-xs font-extrabold uppercase tracking-wider text-brand-navy-700 sm:px-6">{{ $gradeName }}</th>
                        </tr>
                        @foreach ($gradeSections as $item)
                            @php
                                $adviser = $item->teachingAssignments->firstWhere('assignment_type', 'adviser');
                                $activeOfferings = $item->allSectionSubjects->where('status', 'active');
                                $taught = $item->teachingAssignments->where('assignment_type', 'subject_teacher')->pluck('section_subject_id')->filter()->unique()->intersect($activeOfferings->pluck('id'))->count();
                                $openUrl = route('academics.admin.index', [...$yearQuery, 'section' => $item->public_id]);
                            @endphp
                            <tr class="border-t border-border hover:bg-canvas/70">
                                <th scope="row" class="px-5 py-3 font-bold text-brand-navy-900 sm:px-6">
                                    <a href="{{ $openUrl }}" class="hover:underline">{{ $item->name }}</a>
                                    @if ($item->curriculum_id === null)
                                        <span class="ml-1 rounded bg-warning-50 px-1.5 py-0.5 text-xs font-bold text-warning-800">Added manually</span>
                                    @endif
                                </th>
                                <td class="px-3 py-3">{!! $adviser ? e($adviser->staffProfile->user->name) : '<span class="text-muted">Not assigned</span>' !!}</td>
                                <td class="px-3 py-3">{{ $activeOfferings->count() ?: '—' }}</td>
                                <td class="px-3 py-3 {{ $activeOfferings->isNotEmpty() && $taught < $activeOfferings->count() ? 'font-bold text-warning-800' : '' }}">
                                    {{ $activeOfferings->isEmpty() ? '—' : $taught.' / '.$activeOfferings->count() }}
                                </td>
                                <td class="px-3 py-3">{{ $item->enrolled_count }} / {{ $item->capacity }}</td>
                                <td class="px-3 py-3"><x-ui.status-badge :status="$item->status === 'active' ? 'active' : 'archived'" /></td>
                                <td class="px-5 py-3 text-right sm:px-6">
                                    <a href="{{ $openUrl }}" class="inline-flex items-center gap-1 font-bold text-brand-navy-700 hover:underline">
                                        Open<span class="sr-only"> {{ $gradeName }} {{ $item->name }}</span>
                                        <x-ui.icon name="arrow-right" size="size-4" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>
    @endif

    {{-- Extra section (outside the curriculum) for the selected year --}}
    @can('create', \App\Modules\Academics\Models\Section::class)
        @if (in_array($year->status, ['planned', 'open'], true))
            <details class="border-t border-border" @if ($errors->hasAny(['name', 'capacity', 'grade_level_id'])) open @endif>
                <summary class="cursor-pointer list-none px-5 py-4 font-bold text-brand-navy-700 hover:bg-canvas sm:px-6 [&::-webkit-details-marker]:hidden">+ Add a section to {{ $year->code }}</summary>
                <form method="POST" action="{{ route('academics.admin.sections.store') }}" class="grid gap-4 border-t border-border px-5 pb-5 pt-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                    @csrf
                    <input type="hidden" name="academic_year_id" value="{{ $year->id }}">
                    <input type="hidden" name="status" value="active">
                    <x-ui.field for="new_section_grade" label="Grade level">
                        <select id="new_section_grade" name="grade_level_id" required class="jca-input">
                            <option value="">Choose grade</option>
                            @foreach ($grades->where('is_active') as $option)
                                <option value="{{ $option->id }}" @selected((string) old('grade_level_id', $grade?->id) === (string) $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field for="new_section_name" label="Section name">
                        <input id="new_section_name" name="name" required maxlength="100" value="{{ old('name') }}" class="jca-input" placeholder="Faith">
                    </x-ui.field>
                    <x-ui.field for="new_section_capacity" label="Capacity">
                        <input id="new_section_capacity" name="capacity" type="number" min="1" max="100" required value="{{ old('capacity', 15) }}" class="jca-input">
                    </x-ui.field>
                    <x-ui.field for="new_section_order" label="Display order">
                        <input id="new_section_order" name="sort_order" type="number" min="0" max="999" value="{{ old('sort_order', 9) }}" class="jca-input">
                    </x-ui.field>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <p class="mb-3 text-sm text-muted">{{ $publishedCurriculum ? 'The new section automatically gets its grade level’s subjects from the published class program. Set the class times afterwards.' : 'Add subjects to the new section after saving it.' }}</p>
                        <x-ui.button type="submit">Save section</x-ui.button>
                    </div>
                </form>
            </details>
        @endif
    @endcan
</section>
