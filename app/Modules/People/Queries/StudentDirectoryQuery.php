<?php

namespace App\Modules\People\Queries;

use App\Modules\People\Models\StudentProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class StudentDirectoryQuery
{
    /**
     * Sortable columns, mapped to the column that actually orders them. Only
     * keys listed here reach the query builder.
     *
     * @var array<string, string>
     */
    public const SORTS = [
        'name' => 'last_name',
        'student_number' => 'student_number',
        'lrn' => 'lrn',
        'birth_date' => 'birth_date',
        'created_at' => 'created_at',
    ];

    /**
     * Learner-profile flags the registrar reports on, mapped to their filter.
     *
     * @var array<string, string>
     */
    public const FLAGS = [
        'ip' => 'Indigenous Peoples community',
        '4ps' => '4Ps recipient',
        'lwd' => 'Learner with disability',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, StudentProfile>
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $sortColumn = self::SORTS[$filters['sort'] ?? ''] ?? 'last_name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return StudentProfile::query()
            ->with([
                'user',
                'latestEnrollment.gradeLevel',
                'latestEnrollment.academicYear',
            ])
            ->withCount('enrollments')
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $sub) => $this->applySearch($sub, '%'.$search.'%'),
            ))
            ->when($filters['year'] ?? null, fn (Builder $query, $yearId) => $query->whereHas(
                'enrollments',
                fn (Builder $enrollment) => $enrollment->where('academic_year_id', $yearId),
            ))
            ->when($filters['grade'] ?? null, fn (Builder $query, $gradeId) => $query->whereHas(
                'enrollments',
                fn (Builder $enrollment) => $enrollment->where('grade_level_id', $gradeId),
            ))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->whereHas(
                'enrollments',
                fn (Builder $enrollment) => $enrollment->where('status', $status),
            ))
            ->when($filters['flag'] ?? null, fn (Builder $query, $flag) => $this->applyFlag($query, $flag))
            ->orderBy($sortColumn, $direction)
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  Builder<StudentProfile>  $query
     */
    private function applySearch(Builder $query, string $like): void
    {
        $query->where('first_name', 'like', $like)
            ->orWhere('middle_name', 'like', $like)
            ->orWhere('last_name', 'like', $like)
            ->orWhere('student_number', 'like', $like)
            ->orWhere('lrn', 'like', $like)
            ->orWhereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?", [$like])
            ->orWhereHas('enrollments', fn (Builder $enrollment) => $enrollment->where('application_number', 'like', $like));
    }

    /**
     * @param  Builder<StudentProfile>  $query
     * @return Builder<StudentProfile>
     */
    private function applyFlag(Builder $query, string $flag): Builder
    {
        return match ($flag) {
            'ip' => $query->whereNotNull('ip_group')->where('ip_group', '!=', ''),
            '4ps' => $query->where('is_4ps_recipient', true),
            'lwd' => $query->where('is_learner_with_disability', true),
            default => $query,
        };
    }
}
