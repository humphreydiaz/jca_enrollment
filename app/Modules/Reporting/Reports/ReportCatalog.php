<?php

namespace App\Modules\Reporting\Reports;

use App\Models\User;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ReportCatalog
{
    public function __construct(
        private readonly RetentionReport $retention = new RetentionReport,
        private readonly GeneralAveragesReport $averages = new GeneralAveragesReport,
    ) {}

    /** Filters every student-level report shares (pre-final panel: "filtering para mas specific"). */
    private const STUDENT_FILTERS = ['school_year', 'grade', 'section', 'search', 'status'];

    /** Final-grade bands for the grades report. */
    public const GRADE_BANDS = [
        'below_75' => ['label' => 'Below 75', 'min' => 0, 'max' => 74.99],
        '75_79' => ['label' => '75 – 79', 'min' => 75, 'max' => 79.99],
        '80_84' => ['label' => '80 – 84', 'min' => 80, 'max' => 84.99],
        '85_89' => ['label' => '85 – 89', 'min' => 85, 'max' => 89.99],
        '90_94' => ['label' => '90 – 94', 'min' => 90, 'max' => 94.99],
        '95_100' => ['label' => '95 – 100', 'min' => 95, 'max' => 100],
    ];

    /** Columns holding peso amounts: right-aligned, and totalled on print. */
    public const MONEY_COLUMNS = ['gross_total', 'discount_total', 'net_total', 'amount', 'required_amount', 'satisfied_amount'];

    /** @return array<string, array{label: string, description: string, roles: list<string>, filters: list<string>}> */
    public function definitions(): array
    {
        return [
            'enrollments' => ['label' => 'Enrollment', 'description' => 'Application and academic enrollment status.', 'roles' => ['admin', 'registrar'], 'filters' => [...self::STUDENT_FILTERS, 'date_from', 'date_to']],
            'students' => ['label' => 'Students', 'description' => 'Student, grade, and current section roster.', 'roles' => ['admin', 'registrar', 'teacher'], 'filters' => self::STUDENT_FILTERS],
            'requirements' => ['label' => 'Requirements', 'description' => 'Requirement checklist completion without document metadata.', 'roles' => ['admin', 'registrar'], 'filters' => [...self::STUDENT_FILTERS, 'date_from', 'date_to']],
            'sections' => ['label' => 'Sections', 'description' => 'Section capacity and confirmed student counts.', 'roles' => ['admin', 'registrar', 'teacher'], 'filters' => ['school_year', 'grade', 'section', 'status']],
            'billing' => ['label' => 'Billing assessments', 'description' => 'Assessment snapshot totals and status.', 'roles' => ['admin', 'accounting'], 'filters' => [...self::STUDENT_FILTERS, 'date_from', 'date_to']],
            'payments' => ['label' => 'Payments', 'description' => 'Offline payment posting facts without evidence or bank details.', 'roles' => ['admin', 'accounting'], 'filters' => [...self::STUDENT_FILTERS, 'date_from', 'date_to']],
            'discounts' => ['label' => 'Discounts', 'description' => 'Campaign award rank, amount, and lifecycle status.', 'roles' => ['admin', 'accounting'], 'filters' => [...self::STUDENT_FILTERS, 'date_from', 'date_to']],
            'permits' => ['label' => 'Exam permits', 'description' => 'Issued permit status without confidential exception reasons.', 'roles' => ['admin', 'accounting', 'registrar'], 'filters' => [...self::STUDENT_FILTERS, 'date_from', 'date_to']],
            'grades' => ['label' => 'Final grades', 'description' => 'Admin-only finalized four-quarter subject results.', 'roles' => ['admin'], 'filters' => ['school_year', 'grade', 'section', 'search', 'band', 'min_grade', 'max_grade', 'date_from', 'date_to']],
            'communications' => ['label' => 'Communications', 'description' => 'SMS purpose and delivery state without message or recipient content.', 'roles' => ['admin'], 'filters' => ['status', 'date_from', 'date_to']],
            'averages' => ['label' => 'General averages', 'description' => 'Each learner\'s average of approved final grades, lowest grade, and rank in section and grade level; filter by grade range.', 'roles' => ['admin', 'registrar', 'teacher'], 'filters' => ['school_year', 'grade', 'section', 'search', 'band', 'min_grade', 'max_grade']],
            'retention' => ['label' => 'Retention and exits', 'description' => 'Who dropped out, was excluded, transferred out, did not return, is repeating, changed section, or has a final grade below 75.', 'roles' => ['admin', 'registrar'], 'filters' => ['school_year', 'grade', 'section', 'search', 'status']],
        ];
    }

    /** A cell as people should read it (coded values become words); used on screen, print and CSV. */
    public static function displayValue(string $field, mixed $value): mixed
    {
        if ($field === 'outcome' && is_string($value)) {
            return RetentionReport::OUTCOMES[$value] ?? $value;
        }

        return $value;
    }

    /** @return array<string, array{label: string, description: string, roles: list<string>, filters: list<string>}> */
    public function visibleTo(User $user): array
    {
        return array_filter($this->definitions(), fn (array $definition, string $code): bool => $this->allows($user, $code), ARRAY_FILTER_USE_BOTH);
    }

    public function allows(User $user, string $code): bool
    {
        $definition = $this->definitions()[$code] ?? null;

        return $definition !== null && $user->hasAnyRole($definition['roles'])
            && ($code !== 'grades' || $user->isActive());
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
            'requirements' => ['application_number' => 'Application', 'student_number' => 'Student number', 'student_name' => 'Student', 'school_year' => 'School year', 'grade' => 'Grade', 'section' => 'Section', 'requirement' => 'Requirement', 'status' => 'Status', 'reviewed_at' => 'Reviewed'],
            'sections' => ['school_year' => 'School year', 'grade' => 'Grade', 'section' => 'Section', 'capacity' => 'Capacity', 'confirmed_students' => 'Confirmed students', 'status' => 'Status'],
            'billing' => ['assessment_reference' => 'Assessment', 'student_number' => 'Student number', 'student_name' => 'Student', 'school_year' => 'School year', 'grade' => 'Grade', 'section' => 'Section', 'gross_total' => 'Gross total', 'discount_total' => 'Discount total', 'net_total' => 'Net total', 'status' => 'Status', 'finalized_at' => 'Finalized'],
            'payments' => ['payment_reference' => 'Payment', 'receipt_reference' => 'Receipt', 'student_number' => 'Student number', 'student_name' => 'Student', 'school_year' => 'School year', 'grade' => 'Grade', 'section' => 'Section', 'received_on' => 'Received', 'amount' => 'Amount', 'method' => 'Method', 'status' => 'Status'],
            'discounts' => ['campaign' => 'Campaign', 'rank' => 'Rank', 'student_number' => 'Student number', 'student_name' => 'Student', 'school_year' => 'School year', 'grade' => 'Grade', 'section' => 'Section', 'amount' => 'Amount', 'status' => 'Status', 'awarded_at' => 'Awarded'],
            'permits' => ['permit_reference' => 'Permit', 'student_number' => 'Student number', 'student_name' => 'Student', 'school_year' => 'School year', 'grade' => 'Grade', 'section' => 'Section', 'exam_period' => 'Exam period', 'status' => 'Status', 'issued_at' => 'Issued'],
            'grades' => ['student_number' => 'Student number', 'student_name' => 'Student', 'school_year' => 'School year', 'grade' => 'Grade', 'section' => 'Section', 'subject' => 'Subject', 'quarter_1' => 'Q1', 'quarter_2' => 'Q2', 'quarter_3' => 'Q3', 'quarter_4' => 'Q4', 'final_grade' => 'Final grade', 'finalized_at' => 'Finalized'],
            'communications' => ['notification_reference' => 'Notification', 'purpose' => 'Purpose', 'status' => 'Status', 'provider' => 'Provider', 'attempt_count' => 'Attempts', 'scheduled_at' => 'Scheduled', 'accepted_at' => 'Accepted', 'failed_at' => 'Failed'],
            'averages' => ['student_number' => 'Student number', 'student_name' => 'Student', 'school_year' => 'School year', 'grade' => 'Grade', 'section' => 'Section', 'subjects' => 'Approved subjects', 'general_average' => 'Average', 'lowest_grade' => 'Lowest grade', 'rank_in_section' => 'Rank in section', 'rank_in_grade' => 'Rank in grade'],
            'retention' => ['student_number' => 'Student number', 'student_name' => 'Student', 'school_year' => 'Last enrolled in', 'grade' => 'Grade', 'section' => 'Section', 'outcome' => 'Outcome', 'details' => 'Details', 'outcome_date' => 'Date'],
            default => [],
        };
        if ($code === 'permits' && $user->hasAnyRole(['admin', 'accounting'])) {
            $columns = array_slice($columns, 0, 7, true) + ['required_amount' => 'Required amount', 'satisfied_amount' => 'Satisfied amount'] + array_slice($columns, 7, null, true);
        }

        return $columns;
    }

    /**
     * The allowlisted columns the viewer picked, in catalog order; all of them when none (or none valid) were picked.
     *
     * @param  list<string>|null  $picked
     * @return array<string, string>
     */
    public function selectedColumns(string $code, User $user, ?array $picked): array
    {
        $columns = $this->columns($code, $user);
        if ($picked === null || $picked === []) {
            return $columns;
        }
        $chosen = array_intersect_key($columns, array_flip($picked));

        return $chosen === [] ? $columns : $chosen;
    }

    /**
     * Distinct status values the report can filter on (for the Status dropdown).
     *
     * @return list<string>
     */
    public function statusOptions(string $code): array
    {
        [$table, $column] = match ($code) {
            'enrollments', 'students' => ['enrollments', 'status'], 'requirements' => ['enrollment_requirements', 'status'],
            'sections' => ['sections', 'status'], 'billing' => ['fee_assessments', 'status'], 'payments' => ['payments', 'status'],
            'discounts' => ['discount_awards', 'status'], 'permits' => ['exam_permits', 'status'], 'communications' => ['notifications', 'status'],
            default => [null, null],
        };
        if ($code === 'retention') {
            return array_keys(RetentionReport::OUTCOMES);
        }

        return $table === null ? [] : DB::table($table)->whereNotNull($column)->distinct()->orderBy($column)->pluck($column)->map(fn ($value) => (string) $value)->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $rowKeys  limit to these rows (print "selected rows"); empty means no limit
     */
    public function query(string $code, User $user, array $filters, array $rowKeys = []): Builder
    {
        if ($code === 'grades' && ! $this->allows($user, $code)) {
            abort(403);
        }

        $query = match ($code) {
            'enrollments' => $this->enrollments(),
            'students' => $this->students($user),
            'requirements' => $this->requirements(),
            'sections' => $this->sections($user),
            'billing' => $this->billing(),
            'payments' => $this->payments(),
            'discounts' => $this->discounts(),
            'permits' => $this->permits(),
            'grades' => $this->grades(),
            'communications' => $this->communications(),
            'retention' => $this->retention->query($filters),
            'averages' => $this->averagesFor($user),
            default => throw new \InvalidArgumentException('Unknown report.'),
        };

        // An opaque key per row (public ULIDs, never internal ids) so a viewer can tick rows to print.
        $rowKey = $this->rowKeyExpression($code);
        if (! in_array($code, ['retention', 'averages'], true)) {
            $query->addSelect(DB::raw($rowKey.' as row_key'));
        }
        if ($rowKeys !== []) {
            $query->whereIn(DB::raw($rowKey), $rowKeys);
        }

        return $this->applySort($this->applyFilters($query, $code, $filters), $code, $user, $filters);
    }

    private function rowKeyExpression(string $code): string
    {
        return match ($code) {
            'enrollments', 'students' => 'e.public_id',
            'requirements' => "CONCAT(e.public_id, '.', dt.code)",
            'sections' => 'sec.public_id',
            'billing' => 'fa.public_id',
            'payments' => 'p.public_id',
            'discounts' => 'da.public_id',
            'permits' => 'ep.public_id',
            'grades' => 'sgr.public_id',
            'communications' => 'n.public_id',
            'retention', 'averages' => 'rt.row_key',
        };
    }

    /** The learner's current confirmed section, for reports that start from an enrollment. */
    private function withCurrentSection(Builder $query): Builder
    {
        return $query->leftJoin('section_assignments as sa', fn ($join) => $join->on('sa.enrollment_id', '=', 'e.id')->where('sa.status', 'confirmed')->whereNull('sa.ended_at'))
            ->leftJoin('sections as sec', 'sec.id', '=', 'sa.section_id');
    }

    /** Shared joins for reports that start from an enrollment row aliased `e`. */
    private function enrollmentJoins(Builder $query): Builder
    {
        return $this->withCurrentSection($query->join('student_profiles as s', 's.id', '=', 'e.student_id')
            ->join('academic_years as ay', 'ay.id', '=', 'e.academic_year_id')
            ->join('grade_levels as g', 'g.id', '=', 'e.grade_level_id'));
    }

    private function studentName(): Expression
    {
        return DB::raw("CONCAT(s.last_name, ', ', s.first_name) as student_name");
    }

    private function enrollments(): Builder
    {
        return $this->enrollmentJoins(DB::table('enrollments as e'))
            ->select(['e.application_number', 's.student_number', $this->studentName(), 'ay.name as school_year', 'g.name as grade', 'sec.name as section', 'e.student_category as category', 'e.status', 'e.submitted_at', 'e.approved_at'])
            ->orderBy('ay.starts_on', 'desc')->orderBy('s.last_name');
    }

    private function students(User $user): Builder
    {
        $query = $this->enrollmentJoins(DB::table('enrollments as e'))
            ->select(['s.student_number', $this->studentName(), 'ay.name as school_year', 'g.name as grade', 'sec.name as section', 'e.status'])
            ->orderBy('s.last_name');
        if ($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'registrar'])) {
            $this->scopeTeacherSections($query, $user, 'sec.id');
        }

        return $query;
    }

    private function requirements(): Builder
    {
        $name = "CASE WHEN dt.code = 'FORM_138' THEN 'School Form 9 (SF9)' ELSE dt.name END";

        return $this->enrollmentJoins(DB::table('enrollment_requirements as r')->join('enrollments as e', 'e.id', '=', 'r.enrollment_id')->join('document_types as dt', 'dt.id', '=', 'r.document_type_id'))
            ->select(['e.application_number', 's.student_number', $this->studentName(), 'ay.name as school_year', 'g.name as grade', 'sec.name as section', DB::raw($name.' as requirement'), 'r.status', 'r.reviewed_at'])
            ->orderBy('s.last_name')->orderByRaw($name);
    }

    private function sections(User $user): Builder
    {
        $query = DB::table('sections as sec')->join('academic_years as ay', 'ay.id', '=', 'sec.academic_year_id')->join('grade_levels as g', 'g.id', '=', 'sec.grade_level_id')
            ->leftJoin('section_assignments as sa', fn ($join) => $join->on('sa.section_id', '=', 'sec.id')->where('sa.status', 'confirmed')->whereNull('sa.ended_at'))
            ->select(['ay.name as school_year', 'g.name as grade', 'sec.name as section', 'sec.capacity', 'sec.status'])->selectRaw('COUNT(DISTINCT sa.id) as confirmed_students')
            ->groupBy('ay.name', 'ay.starts_on', 'g.name', 'g.sequence', 'sec.id', 'sec.public_id', 'sec.name', 'sec.capacity', 'sec.status')
            ->orderBy('ay.starts_on', 'desc')->orderBy('g.sequence')->orderBy('sec.name');
        if ($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'registrar'])) {
            $this->scopeTeacherSections($query, $user, 'sec.id');
        }

        return $query;
    }

    private function billing(): Builder
    {
        return $this->enrollmentJoins(DB::table('fee_assessments as fa')->join('enrollments as e', 'e.id', '=', 'fa.enrollment_id'))
            ->select(['fa.assessment_reference', 's.student_number', $this->studentName(), 'ay.name as school_year', 'g.name as grade', 'sec.name as section', 'fa.gross_total', 'fa.discount_total', 'fa.net_total', 'fa.status', 'fa.finalized_at'])
            ->orderByDesc('fa.created_at');
    }

    private function payments(): Builder
    {
        return $this->enrollmentJoins(DB::table('payments as p')->join('fee_assessments as fa', 'fa.id', '=', 'p.fee_assessment_id')->join('enrollments as e', 'e.id', '=', 'fa.enrollment_id'))
            ->select(['p.payment_reference', 'p.receipt_reference', 's.student_number', $this->studentName(), 'ay.name as school_year', 'g.name as grade', 'sec.name as section', 'p.received_on', 'p.amount', 'p.method', 'p.status'])
            ->orderByDesc('p.received_on');
    }

    private function discounts(): Builder
    {
        return $this->enrollmentJoins(DB::table('discount_awards as da')->join('discount_campaigns as dc', 'dc.id', '=', 'da.discount_campaign_id')->join('enrollments as e', 'e.id', '=', 'da.enrollment_id'))
            ->select(['dc.name as campaign', 'da.rank', 's.student_number', $this->studentName(), 'ay.name as school_year', 'g.name as grade', 'sec.name as section', 'da.amount', 'da.status', 'da.awarded_at'])
            ->orderBy('dc.name')->orderBy('da.rank');
    }

    private function permits(): Builder
    {
        return $this->enrollmentJoins(DB::table('exam_permits as ep')->join('exam_periods as period', 'period.id', '=', 'ep.exam_period_id')->join('enrollments as e', 'e.id', '=', 'ep.enrollment_id'))
            ->select(['ep.permit_reference', 's.student_number', $this->studentName(), 'ay.name as school_year', 'g.name as grade', 'sec.name as section', 'period.name as exam_period', 'ep.required_amount', 'ep.satisfied_amount', 'ep.status', 'ep.issued_at'])
            ->orderByDesc('ep.issued_at');
    }

    private function communications(): Builder
    {
        return DB::table('notifications as n')->leftJoin('sms_messages as sm', 'sm.notification_id', '=', 'n.id')
            ->select(['n.public_id as notification_reference', 'n.purpose', 'n.status', 'sm.provider', 'sm.attempt_count', 'n.scheduled_at', 'n.accepted_at', 'n.failed_at'])
            ->orderByDesc('n.created_at');
    }

    private function grades(): Builder
    {
        return $this->enrollmentJoins(DB::table('student_subject_grades as sgr')->join('enrollments as e', 'e.id', '=', 'sgr.enrollment_id'))
            ->where('sgr.status', 'finalized')
            ->select(['s.student_number', $this->studentName(), 'ay.name as school_year', 'g.name as grade', 'sec.name as section', 'sgr.subject_name_snapshot as subject',
                'sgr.quarter_1', 'sgr.quarter_2', 'sgr.quarter_3', 'sgr.quarter_4', 'sgr.final_grade', 'sgr.finalized_at'])
            ->orderByDesc('ay.starts_on')->orderBy('s.last_name')->orderBy('sgr.subject_name_snapshot');
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, string $code, array $filters): Builder
    {
        if (in_array($code, ['retention', 'averages'], true)) {
            return $this->applyDerivedFilters($query, $code, $filters);
        }
        $academic = $code !== 'communications';
        if ($academic && filled($filters['school_year'] ?? null)) {
            $query->where('ay.public_id', $filters['school_year']);
        }
        if ($academic && filled($filters['grade'] ?? null)) {
            $query->where('g.public_id', $filters['grade']);
        }
        if ($academic && filled($filters['section'] ?? null)) {
            $query->where('sec.public_id', $filters['section']);
        }
        // "Sino lang": one learner, by name or Student ID.
        if ($academic && $code !== 'sections' && filled($filters['search'] ?? null)) {
            $term = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->where(fn (Builder $search) => $search->where('s.student_number', 'like', $term)
                ->orWhere('s.first_name', 'like', $term)->orWhere('s.last_name', 'like', $term)
                ->orWhereRaw("CONCAT(s.first_name, ' ', s.last_name) like ?", [$term])
                ->orWhereRaw("CONCAT(s.last_name, ', ', s.first_name) like ?", [$term]));
        }
        $statusColumn = match ($code) {
            'enrollments', 'students' => 'e.status', 'requirements' => 'r.status', 'sections' => 'sec.status', 'billing' => 'fa.status', 'payments' => 'p.status', 'discounts' => 'da.status', 'permits' => 'ep.status', 'communications' => 'n.status', default => null,
        };
        if ($statusColumn !== null && filled($filters['status'] ?? null)) {
            $query->where($statusColumn, $filters['status']);
        }
        if ($code === 'grades') {
            $band = self::GRADE_BANDS[$filters['band'] ?? ''] ?? null;
            $min = $band['min'] ?? ($filters['min_grade'] ?? null);
            $max = $band['max'] ?? ($filters['max_grade'] ?? null);
            if (filled($min)) {
                $query->where('sgr.final_grade', '>=', $min);
            }
            if (filled($max)) {
                $query->where('sgr.final_grade', '<=', $max);
            }
        }
        $dateColumn = match ($code) {
            'enrollments' => 'e.submitted_at', 'requirements' => 'r.reviewed_at', 'billing' => 'fa.finalized_at', 'payments' => 'p.received_on', 'discounts' => 'da.awarded_at', 'permits' => 'ep.issued_at', 'grades' => 'sgr.finalized_at', 'communications' => 'n.created_at', default => null,
        };
        if ($dateColumn !== null && filled($filters['date_from'] ?? null)) {
            $query->whereDate($dateColumn, '>=', $filters['date_from']);
        }
        if ($dateColumn !== null && filled($filters['date_to'] ?? null)) {
            $query->whereDate($dateColumn, '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * The retention rows are one derived table (`rt`); the year itself is chosen inside RetentionReport.
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyDerivedFilters(Builder $query, string $code, array $filters): Builder
    {
        if ($code === 'averages' && filled($filters['school_year'] ?? null)) {
            $query->where('rt.year_public', $filters['school_year']);
        }
        if ($code === 'averages') {
            $band = self::GRADE_BANDS[$filters['band'] ?? ''] ?? null;
            $min = $band['min'] ?? ($filters['min_grade'] ?? null);
            $max = $band['max'] ?? ($filters['max_grade'] ?? null);
            if (filled($min)) {
                $query->where('rt.general_average', '>=', $min);
            }
            if (filled($max)) {
                $query->where('rt.general_average', '<=', $max);
            }
        }
        if (filled($filters['grade'] ?? null)) {
            $query->where('rt.grade_public', $filters['grade']);
        }
        if (filled($filters['section'] ?? null)) {
            $query->where('rt.section_public', $filters['section']);
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('rt.outcome', $filters['status']);
        }
        if (filled($filters['search'] ?? null)) {
            $term = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->where(fn (Builder $search) => $search->where('rt.student_number', 'like', $term)
                ->orWhere('rt.first_name', 'like', $term)->orWhere('rt.last_name', 'like', $term)
                ->orWhere('rt.student_name', 'like', $term));
        }

        return $query;
    }

    /** General averages; a teacher sees only sections they teach or advise. */
    private function averagesFor(User $user): Builder
    {
        $query = $this->averages->query();
        if ($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'registrar'])) {
            $this->scopeTeacherSections($query, $user, 'rt.section_id');
        }

        return $query;
    }

    /** @return array<string, mixed> */
    public function retentionSummary(array $filters): array
    {
        return $this->retention->summary($filters);
    }

    /**
     * Order by any allowlisted column ("in order"), then by learner name so equal values stay stable.
     *
     * @param  array<string, mixed>  $filters
     */
    private function applySort(Builder $query, string $code, User $user, array $filters): Builder
    {
        $columns = $this->columns($code, $user);
        $sort = $filters['sort'] ?? null;
        if (! is_string($sort) || ! array_key_exists($sort, $columns)) {
            return $query;
        }
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        // $sort is an allowlisted select alias, so quoting it into ORDER BY is safe.
        $query->reorder()->orderByRaw('`'.$sort.'` '.$direction);
        if ($sort !== 'student_name' && array_key_exists('student_name', $columns)) {
            $query->orderByRaw('`student_name` asc');
        }

        return $query;
    }

    private function scopeTeacherSections(Builder $query, User $user, string $sectionColumn): void
    {
        $query->whereExists(function (Builder $assigned) use ($user, $sectionColumn): void {
            $assigned->selectRaw('1')->from('teaching_assignments as ta')->join('staff_profiles as sp', 'sp.id', '=', 'ta.staff_profile_id')->whereColumn('ta.section_id', $sectionColumn)->where('sp.user_id', $user->id)->where('ta.is_active', true)->where(fn (Builder $dates) => $dates->whereNull('ta.active_from')->orWhereDate('ta.active_from', '<=', today()))->where(fn (Builder $dates) => $dates->whereNull('ta.active_until')->orWhereDate('ta.active_until', '>=', today()));
        });
    }

    /**
     * Aggregate enrollment counts by grade level and sex for the Enrolment Data summary.
     *
     * @param  array<string, mixed>  $filters
     * @return array{stages: array<string, list<array{grade: string, male: int, female: int, total: int}>>, school_year_name: string|null}
     */
    public function enrollmentSummary(array $filters): array
    {
        $query = DB::table('enrollments as e')
            ->join('student_profiles as s', 's.id', '=', 'e.student_id')
            ->join('grade_levels as g', 'g.id', '=', 'e.grade_level_id')
            ->join('academic_years as ay', 'ay.id', '=', 'e.academic_year_id')
            ->select([
                'g.name as grade',
                'g.school_stage',
                'g.sequence',
                's.sex',
                DB::raw('COUNT(*) as count'),
            ])
            ->whereIn('e.status', ['approved', 'enrolled'])
            ->where('g.is_active', true)
            ->groupBy('g.id', 'g.name', 'g.school_stage', 'g.sequence', 's.sex')
            ->orderBy('g.sequence');

        // Apply school year filter — default to the most recent active year when unfiltered
        if (filled($filters['school_year'] ?? null)) {
            $query->where('ay.public_id', $filters['school_year']);
        } else {
            $latestYear = DB::table('academic_years')->orderByDesc('starts_on')->first();
            if ($latestYear) {
                $query->where('ay.id', $latestYear->id);
            }
        }

        $rows = $query->get();

        // Determine the school year name for the heading
        $schoolYearName = null;
        if (filled($filters['school_year'] ?? null)) {
            $schoolYearName = DB::table('academic_years')->where('public_id', $filters['school_year'])->value('name');
        } else {
            $schoolYearName = DB::table('academic_years')->orderByDesc('starts_on')->value('name');
        }

        // Build structured data grouped by school_stage
        $stages = [];
        foreach ($rows as $row) {
            $stage = $row->school_stage ?? 'Other';
            if (! isset($stages[$stage])) {
                $stages[$stage] = [];
            }
            $grade = $row->grade;
            if (! isset($stages[$stage][$grade])) {
                $stages[$stage][$grade] = ['grade' => $grade, 'male' => 0, 'female' => 0, 'total' => 0];
            }
            $sex = strtolower($row->sex ?? '');
            if ($sex === 'male') {
                $stages[$stage][$grade]['male'] += (int) $row->count;
            } else {
                $stages[$stage][$grade]['female'] += (int) $row->count;
            }
            $stages[$stage][$grade]['total'] += (int) $row->count;
        }

        // Convert to indexed arrays and order by expected stage sequence
        $orderedStages = [];
        $stageOrder = ['Pre-elementary', 'Elementary', 'Junior High School'];
        foreach ($stageOrder as $stageKey) {
            if (isset($stages[$stageKey])) {
                $orderedStages[$stageKey] = array_values($stages[$stageKey]);
            }
        }
        // Include any remaining stages not in the ordered list
        foreach ($stages as $stageKey => $grades) {
            if (! isset($orderedStages[$stageKey])) {
                $orderedStages[$stageKey] = array_values($grades);
            }
        }

        return [
            'stages' => $orderedStages,
            'school_year_name' => $schoolYearName,
        ];
    }
}
