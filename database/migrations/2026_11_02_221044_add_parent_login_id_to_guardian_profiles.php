<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardian_profiles', function (Blueprint $table) {
            $table->string('parent_login_id', 10)->nullable()->unique();
        });

        $year = now('Asia/Manila')->format('y');

        $existingIds = DB::table('users')
            ->where('public_id', 'like', 'PAR-%')
            ->pluck('public_id')
            ->all();

        $highest = 0;
        $reserved = [];

        foreach ($existingIds as $id) {
            $reserved[$id] = true;

            if (preg_match('/^PAR-' . $year . '-(\d{3})$/', $id, $matches)) {
                $highest = max($highest, (int) $matches[1]);
            }
        }

        $guardians = DB::table('guardian_profiles as g')
            ->join('users as u', 'u.id', '=', 'g.user_id')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('guardian_student as gs')
                    ->join('enrollments as e', 'e.student_id', '=', 'gs.student_id')
                    ->join('grade_levels as gl', 'gl.id', '=', 'e.grade_level_id')
                    ->whereColumn('gs.guardian_id', 'g.id')
                    ->whereNull('gs.ended_at')
                    ->whereIn('gl.code', ['G7', 'G8', 'G9', 'G10']);
            })
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('user_roles as ur')
                    ->join('roles as r', 'r.id', '=', 'ur.role_id')
                    ->whereColumn('ur.user_id', 'u.id')
                    ->whereNull('ur.revoked_at')
                    ->where('r.code', 'guardian');
            })
            ->select('g.id', 'u.public_id')
            ->distinct()
            ->orderByRaw("CASE WHEN u.public_id = 'PAR-{$year}-000' THEN 0 ELSE 1 END")
            ->orderBy('g.id')
            ->get();

        foreach ($guardians as $guardian) {
            $existing = strtoupper($guardian->public_id);

            if (preg_match('/^PAR-\d{2}-(?!000)\d{3}$/', $existing)) {
                $parentId = $existing;
            } else {
                do {
                    $highest++;

                    if ($highest > 999) {
                        throw new RuntimeException('Parent ID limit reached.');
                    }

                    $parentId = sprintf('PAR-%s-%03d', $year, $highest);
                } while (isset($reserved[$parentId]));
            }

            DB::table('guardian_profiles')
                ->where('id', $guardian->id)
                ->update(['parent_login_id' => $parentId]);

            $reserved[$parentId] = true;
        }
    }

    public function down(): void
    {
        Schema::table('guardian_profiles', function (Blueprint $table) {
            $table->dropUnique(['parent_login_id']);
            $table->dropColumn('parent_login_id');
        });
    }
};
