<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Models\User;
use App\Modules\Identity\Actions\ReissueTemporaryPasswordAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ReissueTemporaryPasswordController
{
    public function index(): Response
    {
        return response()
            ->view('identity.admin.reissue-temporary-password')
            ->header('Cache-Control', 'no-store, private');
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9-]+$/',
            ],
        ]);

        $user = $this->findAccount($validated['account_id']);

        if ($user && ! $this->canManage($request->user(), $user)) {
            $user = null;
        }

        return response()->json([
            'found' => $user !== null,
            'name' => $user?->name,
            'role' => $user
                ? $user->roles->pluck('name')->implode(', ')
                : null,
            'status' => $user
                ? ($user->disabled_at
                    ? 'Disabled'
                    : ($user->locked_at ? 'Locked' : 'Found'))
                : null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function reissue(
        Request $request,
        ReissueTemporaryPasswordAction $reissue
    ): RedirectResponse {
        $validated = $request->validate([
            'account_id' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9-]+$/',
            ],
            'identity_confirmed' => [
                'required',
                'accepted',
            ],
        ]);

        $accountId = strtoupper(trim($validated['account_id']));
        $user = $this->findAccount($accountId);

        if (! $user) {
            throw ValidationException::withMessages([
                'account_id' => 'No matching login account was found.',
            ]);
        }

        abort_unless(
            $this->canManage($request->user(), $user),
            403
        );

        $result = $reissue->execute($user, $request->user());

        return redirect()
            ->route('identity.users.reissue.index')
            ->with('reissued_access', [
                'account_id' => $accountId,
                'name' => $user->name,
                'temporary_password' => $result['temporary_password'],
                'expires_at' => $result['expires_at']
                    ->format('M d, Y h:i A'),
            ]);
    }

    private function canManage(User $actor, User $user): bool
    {
        if ($actor->is($user)) {
            return false;
        }

        if ($actor->hasRole('admin')) {
            return true;
        }

        return $actor->hasRole('registrar')
            && (
                $user->hasRole('student')
                || $user->hasRole('guardian')
            );
    }

    private function findAccount(string $identifier): ?User
    {
        $identifier = strtoupper(trim($identifier));

        // Parent ID: Grade 7-10.
        if (
            Schema::hasTable('guardian_profiles')
            && Schema::hasColumn(
                'guardian_profiles',
                'parent_login_id'
            )
            && preg_match('/^PAR-\d{2}-\d{3}$/', $identifier)
        ) {
            $ids = DB::table('guardian_profiles')
                ->where('parent_login_id', $identifier)
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id');

            return $this->singleUser($ids);
        }

        // Student ID.
        if (
            Schema::hasTable('student_profiles')
            && Schema::hasColumn(
                'student_profiles',
                'student_number'
            )
        ) {
            $students = DB::table('student_profiles')
                ->where('student_number', $identifier)
                ->get();

            if ($students->count() > 1) {
                return null;
            }

            $student = $students->first();

            if ($student) {
                // Student-owned login.
                if ($student->user_id) {
                    $user = User::with('roles')
                        ->find($student->user_id);

                    if ($user && $user->hasRole('student')) {
                        return $user;
                    }
                }

                // Guardian-owned login.
                if (
                    Schema::hasTable('guardian_student')
                    && Schema::hasTable('guardian_profiles')
                ) {
                    $guardianIds = DB::table(
                        'guardian_student as gs'
                    )
                        ->join(
                            'guardian_profiles as gp',
                            'gp.id',
                            '=',
                            'gs.guardian_id'
                        )
                        ->where(
                            'gs.student_id',
                            $student->id
                        )
                        ->whereNull('gs.ended_at')
                        ->where('gs.may_manage_enrollment', 1)
                        ->whereNotNull('gp.user_id')
                        ->distinct()
                        ->pluck('gp.user_id');

                    $guardian = $this->singleUser($guardianIds);

                    if (
                        $guardian
                        && $guardian->hasRole('guardian')
                    ) {
                        return $guardian;
                    }
                }

                return null;
            }
        }

        // Staff IDs.
        if (Schema::hasTable('staff_profiles')) {
            foreach (
                [
                    'employee_number',
                    'staff_number',
                    'staff_id',
                ] as $column
            ) {
                if (! Schema::hasColumn('staff_profiles', $column)) {
                    continue;
                }

                $ids = DB::table('staff_profiles')
                    ->where($column, $identifier)
                    ->whereNotNull('user_id')
                    ->distinct()
                    ->pluck('user_id');

                if ($ids->isNotEmpty()) {
                    return $this->singleUser($ids);
                }
            }
        }

        // Existing public user ID.
        return User::with('roles')
            ->where('public_id', $identifier)
            ->first();
    }

    private function singleUser($ids): ?User
    {
        $ids = collect($ids)
            ->filter()
            ->unique()
            ->values();

        if ($ids->count() !== 1) {
            return null;
        }

        return User::with('roles')
            ->find($ids->first());
    }
}
