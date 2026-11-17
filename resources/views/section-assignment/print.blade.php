<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Section and schedule · {{ $enrollment->application_number }}</title>@if(!app()->environment('testing'))@vite(['resources/css/app.css'])@endif</head>
<body class="bg-white p-6 text-ink sm:p-10">
<main class="mx-auto max-w-3xl">
    <x-print.letterhead />
    <header class="border-b-2 border-brand-navy-900 pb-4">
        <h1 class="mt-2 text-3xl font-black text-brand-navy-900">Confirmed section and schedule</h1>
        <p class="mt-1 text-sm text-muted">Reference {{ $enrollment->application_number }} · generated {{ now()->format('d M Y H:i') }}</p>
    </header>
    <section class="mt-6">
        <h2 class="text-xl font-black">{{ $enrollment->student->full_name }}</h2>
        <p>{{ $enrollment->academicYear->name }} · {{ $enrollment->gradeLevel->name }}</p>
        @if ($assignment = $enrollment->currentSectionAssignment)
            @php
                $adviser = $assignment->section->teachingAssignments->first();
            @endphp
            <p class="mt-4 text-lg font-bold text-brand-navy-900">Section {{ $assignment->section->name }}</p>
            <p class="mt-1">Adviser: {{ $adviser?->staffProfile?->user?->name ?? 'Not yet configured' }}</p>
            <table class="mt-5 w-full border-collapse text-left text-sm">
                <thead>
                    <tr>
                        <th class="border border-border p-2">Subject</th>
                        <th class="border border-border p-2">Schedule</th>
                        <th class="border border-border p-2">Teacher</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assignment->section->sectionSubjects as $subject)
                        <tr>
                            <td class="border border-border p-2">{{ $subject->subject->name }}</td>
                            <td class="border border-border p-2">{{ $subject->schedule_text ?: 'Not yet recorded' }}</td>
                            <td class="border border-border p-2">{{ $subject->teacherNames() ?: 'To be assigned' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="border border-border p-3 text-muted">
                            @if ($assignment->section->activity_schedule)
                                <x-academics.daily-activities :section="$assignment->section" />
                            @else
                                No schedule has been configured.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <p class="mt-4 text-muted">No confirmed section is available.</p>
        @endif
    </section>
    <p class="mt-8 text-xs text-muted">Portal-safe academic placement copy. It intentionally excludes capacity, assignment history, billing, and support-review information.</p>
    <button type="button" onclick="window.print()" class="jca-button jca-button-secondary mt-6 print:hidden">Print</button>
</main>
</body>
</html>
