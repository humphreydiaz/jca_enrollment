<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Grade 7-10 parent accounts issued after 2026_11_02 had their PAR-YY-NNN sign-in ID written
 * into users.public_id (the opaque ULID) instead of guardian_profiles.parent_login_id, which is
 * the column sign-in reads. Those parents could not sign in with the ID on their slip.
 *
 * 1. Copy a PAR ID from users.public_id to parent_login_id when the profile has none and the ID
 *    is free. This keeps the ID that was printed on the slip.
 * 2. Give every user whose public_id is a PAR ID a fresh ULID, so public_id is opaque again.
 *
 * Irreversible: the down() is a no-op because restoring guessable public IDs is never wanted.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')
            ->where('public_id', 'like', 'PAR-%')
            ->select('id', 'public_id')
            ->get();

        foreach ($users as $user) {
            $parId = strtoupper($user->public_id);

            if (preg_match('/^PAR-\d{2}-\d{3}$/', $parId) && ! str_ends_with($parId, '-000')) {
                $taken = DB::table('guardian_profiles')->where('parent_login_id', $parId)->exists();

                if (! $taken) {
                    DB::table('guardian_profiles')
                        ->where('user_id', $user->id)
                        ->where(fn ($q) => $q->whereNull('parent_login_id')->orWhere('parent_login_id', ''))
                        ->update(['parent_login_id' => $parId, 'updated_at' => now()]);
                }
            }

            DB::table('users')->where('id', $user->id)->update(['public_id' => (string) Str::ulid()]);
        }
    }

    public function down(): void
    {
        // Irreversible data repair; see the class comment.
    }
};
