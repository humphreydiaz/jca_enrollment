<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListUsersAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     users: LengthAwarePaginator<int, User>,
     *     roles: Collection<int, Role>,
     *     filters: array{search: ?string, role: ?string, status: ?string, sort: string, per_page: int},
     *     summary: array{total: int, active: int, invited: int, attention: int}
     * }
     */
    public function execute(array $filters): array
    {
        $normalized = [
            'search' => $filters['search'] ?? null,
            'role' => $filters['role'] ?? null,
            'status' => $filters['status'] ?? null,
            'sort' => $filters['sort'] ?? 'name_asc',
            'per_page' => (int) ($filters['per_page'] ?? 15),
        ];

        $query = User::query()->with(['roles', 'staffProfile', 'guardianProfile']);

        if ($normalized['search'] !== null) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $normalized['search']);

            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhereHas('staffProfile', fn (Builder $staff): Builder => $staff->where('employee_number', 'like', "%{$search}%"))
                    ->orWhereHas('guardianProfile', fn (Builder $guardian): Builder => $guardian->where('parent_login_id', 'like', "%{$search}%"));
            });
        }

        if ($normalized['role'] !== null) {
            $query->whereHas('roles', fn (Builder $query): Builder => $query->where('code', $normalized['role']));
        }

        if ($normalized['status'] !== null) {
            if ($normalized['status'] === AccountStatus::Invited->value) {
                $query->whereIn('status', [
                    AccountStatus::Invited->value,
                    AccountStatus::Active->value,
                ]);

                $query->whereHas(
                    'roles',
                    fn (Builder $role): Builder => $role->where('is_staff', true)
                );
            } else {
                $query->where('status', $normalized['status']);
            }
        }

        $this->applySort($query, $normalized['sort']);

        $statusCounts = User::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'users' => $query->paginate($normalized['per_page'])->withQueryString(),
            'roles' => Role::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => $normalized,
            'summary' => [
                'total' => (int) $statusCounts->sum(),
                'active' => (int) $statusCounts->get(AccountStatus::Active->value, 0),
                'invited' => (int) User::query()
                    ->whereIn('status', [
                        AccountStatus::Invited->value,
                        AccountStatus::Active->value,
                    ])
                    ->whereHas(
                        'roles',
                        fn (Builder $role): Builder => $role->where('is_staff', true)
                    )
                    ->count(),
                'attention' => (int) $statusCounts->get(AccountStatus::Disabled->value, 0),
            ],
        ];
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'recent_login' => $query->orderByRaw('last_login_at IS NULL')->orderByDesc('last_login_at')->orderBy('name'),
            default => $query->orderBy('name')->orderBy('id'),
        };
    }
}
