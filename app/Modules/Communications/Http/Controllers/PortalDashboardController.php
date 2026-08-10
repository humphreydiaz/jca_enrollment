<?php

namespace App\Modules\Communications\Http\Controllers;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Billing\Models\ExamPeriod;
use App\Modules\Billing\Models\ExamPermit;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\Payment;
use App\Modules\Communications\Models\Announcement;
use App\Modules\Communications\Models\Notification;
use App\Modules\Communications\Queries\VisibleAnnouncementsQuery;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StudentProfile;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use App\Modules\Shared\Audit\Models\AuditLog;
use App\Modules\StudentSupport\Models\SupportCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PortalDashboardController
{
    public function __invoke(Request $request, VisibleAnnouncementsQuery $announcements): View
    {
        $user = $request->user();

        $viewData = [
            'cards' => $this->cards($user),
            'announcements' => $announcements->forUser($user)->latest('published_at')->limit(4)->get(),
        ];

        if ($user->hasRole('admin')) {
            $viewData['adminData'] = $this->adminData();
        } elseif ($user->hasRole('registrar')) {
            $viewData['registrarData'] = $this->registrarData($user);
        } elseif ($user->hasRole('accounting')) {
            $viewData['accountingData'] = $this->accountingData();
        }

        return view('communications.portal.dashboard', $viewData);
    }

    /** @return list<array{label: string, value: int, detail: string, route: string}> */
    private function cards(User $user): array
    {
        $cards = [];
        if ($user->hasRole('admin')) {
            $cards[] = ['label' => 'Enrollment review', 'value' => Enrollment::query()->whereIn('status', ['submitted', 'under_review', 'incomplete'])->count(), 'detail' => 'Applications needing attention', 'route' => route('enrollment.registrar.index')];
            $cards[] = ['label' => 'SMS follow-up', 'value' => Notification::query()->where('status', 'failed')->count(), 'detail' => 'Failed deliveries', 'route' => route('communications.admin')];
            $cards[] = ['label' => 'Billing work', 'value' => Payment::query()->where('status', 'draft')->count() + FeeAssessment::query()->where('status', 'draft')->count(), 'detail' => 'Draft records awaiting action', 'route' => route('billing.index')];
            $cards[] = ['label' => 'User accounts', 'value' => User::query()->whereIn('status', [AccountStatus::Invited, AccountStatus::Locked])->count(), 'detail' => 'Invited or locked accounts', 'route' => route('identity.admin.users.index')];
        }
        if ($user->hasRole('registrar')) {
            $cards[] = ['label' => 'Applications', 'value' => Enrollment::query()->whereIn('status', ['submitted', 'under_review', 'incomplete', 'assessment_pending'])->count(), 'detail' => 'Within your review scope', 'route' => route('enrollment.registrar.index')];
            $cards[] = ['label' => 'Awaiting placement', 'value' => Enrollment::query()->where('status', 'approved')->count(), 'detail' => 'Approved, need section assignment', 'route' => route('section-assignment.index')];
            $cards[] = ['label' => 'Support cases', 'value' => SupportCase::query()->where('assigned_reviewer_id', $user->id)->whereNull('closed_at')->count(), 'detail' => 'Open cases assigned to you', 'route' => route('support.queue')];
            $cards[] = ['label' => 'Enrolled students', 'value' => Enrollment::query()->where('status', 'enrolled')->count(), 'detail' => 'Currently enrolled across all years', 'route' => route('section-assignment.index')];
        }
        if ($user->hasRole('accounting')) {
            $cards[] = ['label' => 'Billing work', 'value' => Payment::query()->where('status', 'draft')->count() + FeeAssessment::query()->where('status', 'draft')->count(), 'detail' => 'Draft assessments or collections', 'route' => route('billing.index')];
            $cards[] = ['label' => 'Needs assessment', 'value' => Enrollment::query()->whereIn('status', ['approved', 'enrolled'])->whereDoesntHave('feeAssessments')->count(), 'detail' => 'Enrollments without fee assessment', 'route' => route('billing.index')];
            $cards[] = ['label' => 'Draft payments', 'value' => Payment::query()->where('status', 'draft')->count(), 'detail' => 'Unposted payments to review', 'route' => route('billing.index')];
            $cards[] = ['label' => 'Permits issued today', 'value' => ExamPermit::query()->where('status', 'issued')->whereDate('issued_at', today())->count(), 'detail' => 'Exam permits issued today', 'route' => route('billing.index')];
        }
        if ($user->hasRole('teacher')) {
            $sections = TeachingAssignment::query()->active()->forUser($user)->pluck('section_id');
            $cards[] = ['label' => 'My students', 'value' => SectionAssignment::query()->whereIn('section_id', $sections)->where('status', 'confirmed')->whereNull('ended_at')->count(), 'detail' => 'Students in active assigned classes', 'route' => route('academics.teacher.assignments')];
        }
        if ($user->hasRole('guardian')) {
            $studentIds = GuardianStudent::query()->where('guardian_id', $user->guardianProfile?->id)->whereNull('ended_at')->whereNotNull('verified_at')->where('may_manage_enrollment', true)->pluck('student_id');
            $cards[] = ['label' => 'My children', 'value' => $studentIds->count(), 'detail' => 'Linked records you may manage', 'route' => route('guardian.enrollment.progress')];
        }
        if ($user->hasRole('student')) {
            $cards[] = ['label' => 'My enrollment', 'value' => Enrollment::query()->where('student_id', $user->studentProfile?->id)->count(), 'detail' => 'Your current and prior records', 'route' => route('student.enrollment.progress')];
        }

        return $cards;
    }

    /** @return array<string, mixed> */
    private function adminData(): array
    {
        $enrollmentCounts = Enrollment::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $activeYear = AcademicYear::query()
            ->where('status', 'open')
            ->first(['id', 'code', 'name', 'starts_on', 'ends_on', 'enrollment_opens_on', 'enrollment_closes_on', 'status']);

        $enrollmentByGrade = $activeYear
            ? Enrollment::query()
                ->where('academic_year_id', $activeYear->id)
                ->whereNotIn('status', Enrollment::TERMINAL_STATUSES)
                ->join('grade_levels', 'enrollments.grade_level_id', '=', 'grade_levels.id')
                ->select('grade_levels.name as grade_name', 'grade_levels.sequence', DB::raw('count(*) as total'))
                ->groupBy('grade_levels.name', 'grade_levels.sequence')
                ->orderBy('grade_levels.sequence')
                ->get()
            : collect();

        $financialSummary = [
            'draft_payments' => Payment::query()->where('status', 'draft')->count(),
            'draft_assessments' => FeeAssessment::query()->where('status', 'draft')->count(),
            'posted_total_today' => Payment::query()->where('status', 'posted')->whereDate('posted_at', today())->sum('amount'),
            'posted_total_month' => Payment::query()->where('status', 'posted')->whereMonth('posted_at', now()->month)->whereYear('posted_at', now()->year)->sum('amount'),
        ];

        $userSummary = [
            'total' => User::query()->count(),
            'active' => User::query()->where('status', AccountStatus::Active)->count(),
            'invited' => User::query()->where('status', AccountStatus::Invited)->count(),
            'locked' => User::query()->where('status', AccountStatus::Locked)->count(),
        ];

        $commsSummary = [
            'failed_sms' => Notification::query()->where('status', 'failed')->count(),
            'pending_sms' => Notification::query()->where('status', 'pending')->count(),
            'published_announcements' => Announcement::query()->where('status', 'published')->count(),
            'draft_announcements' => Announcement::query()->where('status', 'draft')->count(),
        ];

        $recentAudit = AuditLog::query()
            ->with('actor:id,name')
            ->latest('occurred_at')
            ->limit(5)
            ->get(['id', 'public_id', 'actor_user_id', 'action', 'subject_type', 'outcome', 'occurred_at']);

        return compact(
            'enrollmentCounts',
            'activeYear',
            'enrollmentByGrade',
            'financialSummary',
            'userSummary',
            'commsSummary',
            'recentAudit',
        );
    }

    /** @return array<string, mixed> */
    private function accountingData(): array
    {
        $activeYear = AcademicYear::query()
            ->where('status', 'open')
            ->first(['id', 'code', 'name', 'starts_on', 'ends_on', 'status']);

        $assessmentCounts = FeeAssessment::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $paymentCounts = Payment::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $collectionSummary = [
            'posted_today' => Payment::query()->where('status', 'posted')->whereDate('posted_at', today())->sum('amount'),
            'posted_this_week' => Payment::query()->where('status', 'posted')->where('posted_at', '>=', now()->startOfWeek())->sum('amount'),
            'posted_this_month' => Payment::query()->where('status', 'posted')->whereMonth('posted_at', now()->month)->whereYear('posted_at', now()->year)->sum('amount'),
        ];

        $assessmentSummary = [
            'total_assessed' => FeeAssessment::query()->where('status', 'finalized')->sum('net_total'),
            'total_collected' => Payment::query()->where('status', 'posted')->sum('amount'),
        ];

        $needsAssessment = Enrollment::query()
            ->whereIn('status', ['approved', 'enrolled'])
            ->whereDoesntHave('feeAssessments')
            ->count();

        $recentPayments = Payment::query()
            ->with(['assessment.enrollment.student:id,first_name,last_name'])
            ->where('status', 'posted')
            ->latest('posted_at')
            ->limit(5)
            ->get(['id', 'public_id', 'payment_reference', 'receipt_reference', 'fee_assessment_id', 'amount', 'method', 'posted_at']);

        $examPeriods = $activeYear
            ? ExamPeriod::query()
                ->where('academic_year_id', $activeYear->id)
                ->orderBy('starts_on')
                ->get(['id', 'name', 'starts_on', 'ends_on', 'issue_opens_on', 'issue_closes_on', 'status'])
                ->each(function ($period) {
                    $period->issued_count = ExamPermit::query()->where('exam_period_id', $period->id)->where('status', 'issued')->count();
                })
            : collect();

        $paymentMethods = Payment::query()
            ->where('status', 'posted')
            ->select('method', DB::raw('count(*) as total'), DB::raw('sum(amount) as amount'))
            ->groupBy('method')
            ->orderByDesc('amount')
            ->get();

        return compact(
            'activeYear',
            'assessmentCounts',
            'paymentCounts',
            'collectionSummary',
            'assessmentSummary',
            'needsAssessment',
            'recentPayments',
            'examPeriods',
            'paymentMethods',
        );
    }

    /** @return array<string, mixed> */
    private function registrarData(User $user): array
    {
        $activeYear = AcademicYear::query()
            ->where('status', 'open')
            ->first(['id', 'code', 'name', 'starts_on', 'ends_on', 'enrollment_opens_on', 'enrollment_closes_on', 'status']);

        $enrollmentCounts = Enrollment::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $enrollmentByGrade = $activeYear
            ? Enrollment::query()
                ->where('academic_year_id', $activeYear->id)
                ->whereNotIn('status', Enrollment::TERMINAL_STATUSES)
                ->join('grade_levels', 'enrollments.grade_level_id', '=', 'grade_levels.id')
                ->select('grade_levels.name as grade_name', 'grade_levels.sequence', DB::raw('count(*) as total'))
                ->groupBy('grade_levels.name', 'grade_levels.sequence')
                ->orderBy('grade_levels.sequence')
                ->get()
            : collect();

        $sectionCapacity = $activeYear
            ? Section::query()
                ->where('academic_year_id', $activeYear->id)
                ->where('status', 'active')
                ->withCount(['sectionAssignments as confirmed_count' => fn ($q) => $q->where('status', 'confirmed')->whereNull('ended_at')])
                ->with('gradeLevel:id,name,sequence')
                ->orderBy(DB::raw('(SELECT sequence FROM grade_levels WHERE grade_levels.id = sections.grade_level_id)'))
                ->orderBy('sort_order')
                ->get(['id', 'name', 'grade_level_id', 'capacity'])
            : collect();

        $recentEnrollments = Enrollment::query()
            ->with(['student:id,first_name,last_name', 'gradeLevel:id,name'])
            ->whereIn('status', ['submitted', 'under_review', 'incomplete', 'assessment_pending'])
            ->latest('submitted_at')
            ->limit(5)
            ->get(['id', 'public_id', 'application_number', 'student_id', 'grade_level_id', 'status', 'submitted_at']);

        $studentCount = StudentProfile::query()->count();

        $openSupportCases = SupportCase::query()
            ->where('assigned_reviewer_id', $user->id)
            ->whereNull('closed_at')
            ->count();

        return compact(
            'activeYear',
            'enrollmentCounts',
            'enrollmentByGrade',
            'sectionCapacity',
            'recentEnrollments',
            'studentCount',
            'openSupportCases',
        );
    }
}
