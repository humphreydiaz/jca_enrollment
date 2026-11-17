<x-layouts.app title="Subject grades">
    <div class="space-y-6">
        <header class="jca-card p-6 sm:p-8">
            <a class="text-sm font-bold text-brand-navy-700 hover:underline" href="{{ route('grading.teacher.index', ['year' => $assignment->section->academicYear->public_id]) }}">← All my classes</a>
            <h1 class="mt-3 text-3xl font-black text-brand-navy-900">{{ $assignment->sectionSubject->subject->name }}</h1>
            <p class="mt-2 text-sm text-muted">{{ $assignment->section->academicYear->name }} · {{ $assignment->section->gradeLevel->name }} · {{ $assignment->section->name }}</p>
            <p class="mt-3 text-sm text-muted">Enter whole-number quarter grades from 0 to 100. The final grade is calculated only when all four quarters have values. Submitted grades lock until the Registrar approves a reopen request.</p>
        </header>
        <div class="space-y-5">
            @forelse($roster as $placement)
                @php($enrollment = $placement->enrollment)
                @php($grade = $grades->get($enrollment->id))
                @php($pendingCorrection = $grade?->corrections?->firstWhere('status', 'pending'))
                @php($wasReopened = $grade?->status === 'draft' && $grade?->corrections?->contains('status', 'approved'))
                <section class="jca-card p-5 sm:p-6" aria-labelledby="student-{{ $enrollment->public_id }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h2 id="student-{{ $enrollment->public_id }}" class="text-lg font-black text-brand-navy-900">{{ $enrollment->student->full_name }}</h2>
                        <span class="rounded-full bg-brand-blue-50 px-3 py-1 text-xs font-bold text-brand-navy-700">{{ $wasReopened ? 'Reopened draft' : ucfirst($grade?->status ?? 'not started') }}</span>
                    </div>
                    <p class="mt-1 text-sm text-muted">Student ID: {{ $enrollment->student->student_number }}</p>
                    @if($grade === null || $grade->status === 'draft')
                        <form method="POST" action="{{ route('grading.teacher.save', [$assignment, $enrollment]) }}" class="mt-5 space-y-4">
                            @csrf
                            <input type="hidden" name="grade_enrollment" value="{{ $enrollment->public_id }}">
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                @for($quarter = 1; $quarter <= 4; $quarter++)
                                    @php($field = 'quarter_'.$quarter)
                                    <div>
                                        <label class="mb-1 block text-sm font-bold text-brand-navy-900" for="{{ $enrollment->public_id }}-{{ $field }}">{{ $quarter }}{{ ['st','nd','rd','th'][$quarter-1] }} Quarter</label>
                                        <input class="w-full rounded-lg border border-border px-3 py-2 focus:border-brand-navy-700 focus:outline-2 focus:outline-brand-navy-700" id="{{ $enrollment->public_id }}-{{ $field }}" name="{{ $field }}" type="number" min="0" max="100" step="1" inputmode="numeric" value="{{ old('grade_enrollment') === $enrollment->public_id ? old($field) : $grade?->{$field} }}">
                                        @if(old('grade_enrollment') === $enrollment->public_id)
                                            @error($field)
                                                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                            @enderror
                                        @endif
                                    </div>
                                @endfor
                            </div>
                            <p class="text-sm font-bold text-brand-navy-900">Calculated final grade: {{ $grade?->final_grade === null ? 'Incomplete' : $grade->final_grade }}</p>
                            <button class="jca-button-primary" type="submit">Save draft grades</button>
                        </form>
                        @if($grade?->isComplete())
                            <form method="POST" action="{{ route('grading.teacher.submit', [$assignment, $grade]) }}" class="mt-3">@csrf<button class="jca-button-secondary" type="submit">Submit final grade for Registrar approval</button></form>
                        @endif
                    @else
                        <dl class="mt-4 grid gap-3 sm:grid-cols-5">
                            @for($quarter = 1; $quarter <= 4; $quarter++)<div><dt class="text-xs font-bold text-muted">Quarter {{ $quarter }}</dt><dd class="font-black text-brand-navy-900">{{ $grade->{'quarter_'.$quarter} }}</dd></div>@endfor
                            <div><dt class="text-xs font-bold text-muted">Final grade</dt><dd class="font-black text-brand-navy-900">{{ $grade->final_grade ?? 'Incomplete' }}</dd></div>
                        </dl>
                        @if($pendingCorrection !== null)
                            <p class="mt-4 text-sm font-semibold text-brand-navy-700">Reopen request waiting for Registrar review.</p>
                        @else
                            <form method="POST" action="{{ route('grading.teacher.correction', [$assignment, $grade]) }}" class="mt-5 max-w-2xl space-y-2">
                                @csrf
                                <input type="hidden" name="grade_correction" value="{{ $grade->public_id }}">
                                <label class="block text-sm font-bold text-brand-navy-900" for="reason-{{ $grade->public_id }}">Reason for correction</label>
                                <textarea class="w-full rounded-lg border border-border px-3 py-2 focus:border-brand-navy-700 focus:outline-2 focus:outline-brand-navy-700" id="reason-{{ $grade->public_id }}" name="reason" rows="2" minlength="10" maxlength="1000" required>{{ old('grade_correction') === $grade->public_id ? old('reason') : '' }}</textarea>
                                @if(old('grade_correction') === $grade->public_id)
                                    @error('reason')
                                        <p class="text-sm text-red-700">{{ $message }}</p>
                                    @enderror
                                @endif
                                <button class="jca-button-secondary" type="submit">Ask the Registrar to reopen</button>
                            </form>
                        @endif
                    @endif
                </section>
            @empty
                <p class="jca-card p-6 text-muted">No currently confirmed enrolled students are in this section.</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
