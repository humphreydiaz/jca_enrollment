<?php

namespace App\Modules\People\Http\Controllers;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\StudentProfile;
use App\Modules\People\Queries\StudentDirectoryQuery;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StudentDirectoryController
{
    /**
     * Enrollment states a registrar filters the directory by.
     *
     * @var list<string>
     */
    private const STATUSES = [
        'draft', 'submitted', 'under_review', 'incomplete', 'assessment_required',
        'assessment_pending', 'approved', 'enrolled', 'waitlisted', 'rejected',
        'withdrawn', 'cancelled',
    ];

    public function index(Request $request, StudentDirectoryQuery $directory): View
    {
        Gate::authorize('manage', StudentProfile::class);

        $yearId = $request->filled('year')
            ? AcademicYear::query()->where('public_id', $request->string('year'))->value('id')
            : null;
        $gradeId = $request->filled('grade')
            ? GradeLevel::query()->where('public_id', $request->string('grade'))->value('id')
            : null;

        $students = $directory->paginate([
            'search' => $request->string('search')->toString(),
            'year' => $yearId,
            'grade' => $gradeId,
            'status' => in_array($request->string('status')->toString(), self::STATUSES, true) ? $request->string('status')->toString() : null,
            'flag' => array_key_exists($request->string('flag')->toString(), StudentDirectoryQuery::FLAGS) ? $request->string('flag')->toString() : null,
            'sort' => $request->string('sort')->toString(),
            'direction' => $request->string('direction')->toString(),
        ]);

        return view('people.registrar.students.index', [
            'students' => $students,
            'filterYears' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'filterGrades' => GradeLevel::query()->where('is_active', true)->orderBy('sequence')->get(),
            'filterStatuses' => self::STATUSES,
            'filterFlags' => StudentDirectoryQuery::FLAGS,
            'sortOptions' => array_keys(StudentDirectoryQuery::SORTS),
        ]);
    }

    /**
     * The learner record itself. Requested over fetch for the directory modal,
     * and as a standalone page when opened directly or without JavaScript.
     */
    public function show(Request $request, StudentProfile $student, AuditRecorder $audit): View
    {
        Gate::authorize('view', $student);

        $student->load([
            'user',
            'guardians.user',
            'enrollments.gradeLevel',
            'enrollments.academicYear',
            'enrollments.guardian',
            'enrollments.requirements.documentType',
        ]);

        // Learner profiles carry demographic and household data, so each read is
        // recorded the same way a record-room sign-out sheet would be.
        $audit->record($request->user(), 'people.student.record_viewed', $student, [
            'context' => $request->ajax() ? 'directory_modal' : 'record_page',
        ]);

        $enrollments = $student->enrollments->sortByDesc(
            fn (Enrollment $enrollment) => $enrollment->submitted_at ?? $enrollment->created_at,
        )->values();

        if ($request->ajax()) {
            return view('people.registrar.students.partials.record', compact('student', 'enrollments'));
        }

        return view('people.registrar.students.show', compact('student', 'enrollments'));
    }
}
