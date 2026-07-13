<?php

namespace App\Modules\Reporting\Reports;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ReportCatalog
{
    /** @return array<string, array{label: string, description: string, roles: list<string>, filters: list<string>}> */
    public function definitions(): array
    {
        return [
            'enrollments' => ['label' => 'Enrollment', 'description' => 'Application and academic enrollment status.', 'roles' => ['admin', 'registrar'], 'filters' => ['school_year', 'grade', 'section', 'status', 'date_from', 'date_to']],
            'students' => ['label' => 'Students', 'description' => 'Student, grade, and current section roster.', 'roles' => ['admin', 'registrar', 'teacher'], 'filters' => ['school_year', 'grade', 'section', 'status']],
            'requirements' => ['label' => 'Requirements', 'description' => 'Requirement checklist completion without document metadata.', 'roles' => ['admin', 'registrar'], 'filters' => ['school_year', 'grade', 'status', 'date_from', 'date_to']],
            'sections' => ['label' => 'Sections', 'description' => 'Section capacity and confirmed student counts.', 'roles' => ['admin', 'registrar', 'teacher'], 'filters' => ['school_year', 'grade', 'section', 'status']],
            'billing' => ['label' => 'Billing assessments', 'description' => 'Assessment snapshot totals and status.', 'roles' => ['admin', 'accounting'], 'filters' => ['school_year', 'grade', 'status', 'date_from', 'date_to']],
            'payments' => ['label' => 'Payments', 'description' => 'Offline payment posting facts without evidence or bank details.', 'roles' => ['admin', 'accounting'], 'filters' => ['school_year', 'grade', 'status', 'date_from', 'date_to']],
            'discounts' => ['label' => 'Discounts', 'description' => 'Campaign award rank, amount, and lifecycle status.', 'roles' => ['admin', 'accounting'], 'filters' => ['school_year', 'grade', 'status', 'date_from', 'date_to']],
            'permits' => ['label' => 'Exam permits', 'description' => 'Issued permit status without confidential exception reasons.', 'roles' => ['admin', 'accounting', 'registrar'], 'filters' => ['school_year', 'grade', 'status', 'date_from', 'date_to']],
            'communications' => ['label' => 'Communications', 'description' => 'SMS purpose and delivery state without message or recipient content.', 'roles' => ['admin'], 'filters' => ['status', 'date_from', 'date_to']],
        ];
    }

    /** @return array<string, array{label: string, description: string, roles: list<string>, filters: list<string>}> */
    public function visibleTo(User $user): array
    {
        return array_filter($this->definitions(), fn (array $definition): bool => $user->hasAnyRole($definition['roles']));
    }

    public function allows(User $user, string $code): bool
    {
        $definition = $this->definitions()[$code] ?? null;

        return $definition !== null && $user->hasAnyRole($definition['roles']);
    }

    /** @return list<string> */
    public function filters(string $code): array
    {
        return $this->definitions()[$code]['filters'] ?? [];
    }

    /** @return array<string, string> */
    public function columns(string $code, User $user): array
    {
        $columns = match ($code) {
            'enrollments' => ['application_number' => 'Application', 'student_number' => 'Student number', 'student_name' => 'Student', 'school_year' => 'School year', 'grade' => 'Grade', 'section' => 'Section', 'category' => 'Category', 'status' => 'Status', 'submitted_at' => 'Submitted', 'approved_at' => 'Approved'],
            'students' => ['student_number' => 'Student number', 'student_name' => 'Student', 'school_year' => 'School year', 'grade' => 'Grade', 'section' => 'Section', 'status' => 'Enrollment status'],
            'requirements' => ['application_number' => 'Application', 'student_number' => 'Student number', 'student_name' => 'Student', 'school_year' => 'School year', 'grade' => 'Grade', 'requirement' => 'Requirement', 'status' => 'Status', 'reviewed_at' => 'Reviewed'],
            'sections' => ['school_year' => 'School year', 'grade' => 'Grade', 'section' => 'Section', 'capacity' => 'Capacity', 'confirmed_students' => 'Confirmed students', 'status' => 'Status'],
            'billing' => ['assessment_reference' => 'Assessment', 'student_number' => 'Student number', 'student_name' => 'Student', 'school_year' => 'School year', 'grade' => 'Grade', 'gross_total' => 'Gross total', 'discount_total' => 'Discount total', 'net_total' => 'Net total', 'status' => 'Status', 'finalized_at' => 'Finalized'],
            'payments' => ['payment_reference' => 'Payment', 'receipt_reference' => 'Receipt', 'student_number' => 'Student number', 'student_name' => 'Student', 'school_year' => 'School year', 'grade' => 'Grade', 'received_on' => 'Received', 'amount' => 'Amount', 'method' => 'Method', 'status' => 'Status'],
            'discounts' => ['campaign' => 'Campaign', 'rank' => 'Rank', 'student_number' => 'Student number', 'student_name' => 'Student', 'school_year' => 'School year', 'grade' => 'Grade', 'amount' => 'Amount', 'status' => 'Status', 'awarded_at' => 'Awarded'],
            'permits' => ['permit_reference' => 'Permit', 'student_number' => 'Student number', 'student_name' => 'Student', 'school_year' => 'School year', 'grade' => 'Grade', 'exam_period' => 'Exam period', 'status' => 'Status', 'issued_at' => 'Issued'],
            'communications' => ['notification_reference' => 'Notification', 'purpose' => 'Purpose', 'status' => 'Status', 'provider' => 'Provider', 'attempt_count' => 'Attempts', 'scheduled_at' => 'Scheduled', 'accepted_at' => 'Accepted', 'failed_at' => 'Failed'],
            default => [],
        };
        if ($code === 'permits' && $user->hasAnyRole(['admin', 'accounting'])) {
            $columns = array_slice($columns, 0, 6, true) + ['required_amount' => 'Required amount', 'satisfied_amount' => 'Satisfied amount'] + array_slice($columns, 6, null, true);
        }

        return $columns;
    }

    /** @param array<string, mixed> $filters */
    public function query(string $code, User $user, array $filters): Builder
    {
        $query = match ($code) {
            'enrollments' => $this->enrollments(),
            'students' => $this->students($user),
            'requirements' => $this->requirements(),
            'sections' => $this->sections($user),
            'billing' => $this->billing(),
            'payments' => $this->payments(),
            'discounts' => $this->discounts(),
            'permits' => $this->permits(),
            'communications' => $this->communications(),
            default => throw new \InvalidArgumentException('Unknown report.'),
        };

        return $this->applyFilters($query, $code, $filters);
    }

    private function enrollments(): Builder
    {
        return DB::table('enrollments as e')->join('student_profiles as s', 's.id', '=', 'e.student_id')->join('academic_years as ay', 'ay.id', '=', 'e.academic_year_id')->join('grade_levels as g', 'g.id', '=', 'e.grade_level_id')->leftJoin('section_assignments as sa', fn ($join) => $join->on('sa.enrollment_id', '=', 'e.id')->where('sa.status', 'confirmed')->whereNull('sa.ended_at'))->leftJoin('sections as sec', 'sec.id', '=', 'sa.section_id')->select(['e.application_number', 's.student_number', DB::raw("CONCAT(s.last_name, ', ', s.first_name) as student_name"), 'ay.name as school_year', 'g.name as grade', 'sec.name as section', 'e.student_category as category', 'e.status', 'e.submitted_at', 'e.approved_at'])->orderBy('ay.starts_on', 'desc')->orderBy('s.last_name');
    }

    private function students(User $user): Builder
    {
        $query = DB::table('enrollments as e')->join('student_profiles as s', 's.id', '=', 'e.student_id')->join('academic_years as ay', 'ay.id', '=', 'e.academic_year_id')->join('grade_levels as g', 'g.id', '=', 'e.grade_level_id')->leftJoin('section_assignments as sa', fn ($join) => $join->on('sa.enrollment_id', '=', 'e.id')->where('sa.status', 'confirmed')->whereNull('sa.ended_at'))->leftJoin('sections as sec', 'sec.id', '=', 'sa.section_id')->select(['s.student_number', DB::raw("CONCAT(s.last_name, ', ', s.first_name) as student_name"), 'ay.name as school_year', 'g.name as grade', 'sec.name as section', 'e.status'])->orderBy('s.last_name');
        if ($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'registrar'])) {
            $this->scopeTeacherSections($query, $user, 'sec.id');
        }

        return $query;
    }

    private function requirements(): Builder
    {
        return DB::table('enrollment_requirements as r')->join('enrollments as e', 'e.id', '=', 'r.enrollment_id')->join('student_profiles as s', 's.id', '=', 'e.student_id')->join('academic_years as ay', 'ay.id', '=', 'e.academic_year_id')->join('grade_levels as g', 'g.id', '=', 'e.grade_level_id')->join('document_types as dt', 'dt.id', '=', 'r.document_type_id')->select(['e.application_number', 's.student_number', DB::raw("CONCAT(s.last_name, ', ', s.first_name) as student_name"), 'ay.name as school_year', 'g.name as grade', 'dt.name as requirement', 'r.status', 'r.reviewed_at'])->orderBy('s.last_name')->orderBy('dt.name');
    }

    private function sections(User $user): Builder
    {
        $query = DB::table('sections as sec')->join('academic_years as ay', 'ay.id', '=', 'sec.academic_year_id')->join('grade_levels as g', 'g.id', '=', 'sec.grade_level_id')->leftJoin('section_assignments as sa', fn ($join) => $join->on('sa.section_id', '=', 'sec.id')->where('sa.status', 'confirmed')->whereNull('sa.ended_at'))->select(['ay.name as school_year', 'g.name as grade', 'sec.name as section', 'sec.capacity', 'sec.status'])->selectRaw('COUNT(DISTINCT sa.id) as confirmed_students')->groupBy('ay.name', 'ay.starts_on', 'g.name', 'g.sequence', 'sec.id', 'sec.name', 'sec.capacity', 'sec.status')->orderBy('ay.starts_on', 'desc')->orderBy('g.sequence')->orderBy('sec.name');
        if ($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'registrar'])) {
            $this->scopeTeacherSections($query, $user, 'sec.id');
        }

        return $query;
    }

    private function billing(): Builder
    {
        return DB::table('fee_assessments as fa')->join('enrollments as e', 'e.id', '=', 'fa.enrollment_id')->join('student_profiles as s', 's.id', '=', 'e.student_id')->join('academic_years as ay', 'ay.id', '=', 'e.academic_year_id')->join('grade_levels as g', 'g.id', '=', 'e.grade_level_id')->select(['fa.assessment_reference', 's.student_number', DB::raw("CONCAT(s.last_name, ', ', s.first_name) as student_name"), 'ay.name as school_year', 'g.name as grade', 'fa.gross_total', 'fa.discount_total', 'fa.net_total', 'fa.status', 'fa.finalized_at'])->orderByDesc('fa.created_at');
    }

    private function payments(): Builder
    {
        return DB::table('payments as p')->join('fee_assessments as fa', 'fa.id', '=', 'p.fee_assessment_id')->join('enrollments as e', 'e.id', '=', 'fa.enrollment_id')->join('student_profiles as s', 's.id', '=', 'e.student_id')->join('academic_years as ay', 'ay.id', '=', 'e.academic_year_id')->join('grade_levels as g', 'g.id', '=', 'e.grade_level_id')->select(['p.payment_reference', 'p.receipt_reference', 's.student_number', DB::raw("CONCAT(s.last_name, ', ', s.first_name) as student_name"), 'ay.name as school_year', 'g.name as grade', 'p.received_on', 'p.amount', 'p.method', 'p.status'])->orderByDesc('p.received_on');
    }

    private function discounts(): Builder
    {
        return DB::table('discount_awards as da')->join('discount_campaigns as dc', 'dc.id', '=', 'da.discount_campaign_id')->join('enrollments as e', 'e.id', '=', 'da.enrollment_id')->join('student_profiles as s', 's.id', '=', 'e.student_id')->join('academic_years as ay', 'ay.id', '=', 'e.academic_year_id')->join('grade_levels as g', 'g.id', '=', 'e.grade_level_id')->select(['dc.name as campaign', 'da.rank', 's.student_number', DB::raw("CONCAT(s.last_name, ', ', s.first_name) as student_name"), 'ay.name as school_year', 'g.name as grade', 'da.amount', 'da.status', 'da.awarded_at'])->orderBy('dc.name')->orderBy('da.rank');
    }

    private function permits(): Builder
    {
        return DB::table('exam_permits as ep')->join('exam_periods as period', 'period.id', '=', 'ep.exam_period_id')->join('enrollments as e', 'e.id', '=', 'ep.enrollment_id')->join('student_profiles as s', 's.id', '=', 'e.student_id')->join('academic_years as ay', 'ay.id', '=', 'e.academic_year_id')->join('grade_levels as g', 'g.id', '=', 'e.grade_level_id')->select(['ep.permit_reference', 's.student_number', DB::raw("CONCAT(s.last_name, ', ', s.first_name) as student_name"), 'ay.name as school_year', 'g.name as grade', 'period.name as exam_period', 'ep.required_amount', 'ep.satisfied_amount', 'ep.status', 'ep.issued_at'])->orderByDesc('ep.issued_at');
    }

    private function communications(): Builder
    {
        return DB::table('notifications as n')->leftJoin('sms_messages as sm', 'sm.notification_id', '=', 'n.id')->select(['n.public_id as notification_reference', 'n.purpose', 'n.status', 'sm.provider', 'sm.attempt_count', 'n.scheduled_at', 'n.accepted_at', 'n.failed_at'])->orderByDesc('n.created_at');
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, string $code, array $filters): Builder
    {
        $academic = ! in_array($code, ['communications'], true);
        if ($academic && filled($filters['school_year'] ?? null)) {
            $query->where('ay.public_id', $filters['school_year']);
        }
        if ($academic && filled($filters['grade'] ?? null)) {
            $query->where('g.public_id', $filters['grade']);
        }
        if (in_array($code, ['enrollments', 'students', 'sections'], true) && filled($filters['section'] ?? null)) {
            $query->where('sec.public_id', $filters['section']);
        }
        $statusColumn = match ($code) {
            'enrollments', 'students' => 'e.status', 'requirements' => 'r.status', 'sections' => 'sec.status', 'billing' => 'fa.status', 'payments' => 'p.status', 'discounts' => 'da.status', 'permits' => 'ep.status', 'communications' => 'n.status', default => null,
        };
        if ($statusColumn !== null && filled($filters['status'] ?? null)) {
            $query->where($statusColumn, $filters['status']);
        }
        $dateColumn = match ($code) {
            'enrollments' => 'e.submitted_at', 'requirements' => 'r.reviewed_at', 'billing' => 'fa.finalized_at', 'payments' => 'p.received_on', 'discounts' => 'da.awarded_at', 'permits' => 'ep.issued_at', 'communications' => 'n.created_at', default => null,
        };
        if ($dateColumn !== null && filled($filters['date_from'] ?? null)) {
            $query->whereDate($dateColumn, '>=', $filters['date_from']);
        }
        if ($dateColumn !== null && filled($filters['date_to'] ?? null)) {
            $query->whereDate($dateColumn, '<=', $filters['date_to']);
        }

        return $query;
    }

    private function scopeTeacherSections(Builder $query, User $user, string $sectionColumn): void
    {
        $query->whereExists(function (Builder $assigned) use ($user, $sectionColumn): void {
            $assigned->selectRaw('1')->from('teaching_assignments as ta')->join('staff_profiles as sp', 'sp.id', '=', 'ta.staff_profile_id')->whereColumn('ta.section_id', $sectionColumn)->where('sp.user_id', $user->id)->where('ta.is_active', true)->where(fn (Builder $dates) => $dates->whereNull('ta.active_from')->orWhereDate('ta.active_from', '<=', today()))->where(fn (Builder $dates) => $dates->whereNull('ta.active_until')->orWhereDate('ta.active_until', '>=', today()));
        });
    }
}
