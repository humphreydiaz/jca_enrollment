<?php

namespace App\Modules\StudentSupport\Actions;

use App\Models\User;
use App\Modules\People\Models\StudentProfile;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\StudentSupport\Models\ApprovedSupportSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ViewTeacherSafeSummaryAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(User $teacher, StudentProfile $student): ?TeacherStudentSupportView
    {
        $assignment = SectionAssignment::query()
            ->where('status', 'confirmed')
            ->whereNull('ended_at')
            ->whereHas('enrollment', fn (Builder $query) => $query->where('student_id', $student->id)->where('status', 'enrolled'))
            ->whereHas('section.teachingAssignments', fn (Builder $query) => $query->active()->forUser($teacher))
            ->with(['enrollment', 'section.academicYear', 'section.gradeLevel'])
            ->first();

        if ($assignment === null) {
            return null;
        }

        $summary = ApprovedSupportSummary::query()
            ->whereHas('supportCase', fn (Builder $query) => $query->where('enrollment_id', $assignment->enrollment_id))
            ->whereNotNull('approved_at')
            ->whereNull('superseded_by')
            ->where(fn (Builder $query) => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', today()))
            ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))
            ->latest('approved_at')
            ->first();

        if ($summary !== null) {
            DB::table('support_access_logs')->insert(['support_case_id' => $summary->support_case_id, 'actor_user_id' => $teacher->id, 'action' => 'teacher_safe_summary_view', 'purpose' => 'current_instructional_support', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $this->audit->record($teacher, 'support.teacher_safe_summary.viewed', $summary);
        }

        return new TeacherStudentSupportView($assignment, $summary);
    }
}
