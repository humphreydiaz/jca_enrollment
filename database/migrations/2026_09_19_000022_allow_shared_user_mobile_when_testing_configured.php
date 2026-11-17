<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enable household mobile-number reuse only for explicitly configured test
     * deployments. The environment setting must be true before this migration
     * runs; otherwise the production unique index is preserved.
     */
    public function up(): void
    {
        if (! config('enrollment.allow_shared_contact_accounts', false)) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasIndex('users', 'users_mobile_unique')) {
                $table->dropUnique('users_mobile_unique');
            }

            if (! Schema::hasIndex('users', 'users_mobile_index')) {
                $table->index('mobile', 'users_mobile_index');
            }
        });
    }

    /**
     * Restoring uniqueness is possible only after shared-number test accounts
     * have been reconciled. Refuse rollback instead of deleting user records.
     */
    public function down(): void
    {
        $duplicateExists = DB::table('users')
            ->select('mobile')
            ->whereNotNull('mobile')
            ->groupBy('mobile')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateExists) {
            throw new \RuntimeException('Shared mobile test accounts must be reconciled before restoring the unique users.mobile index.');
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasIndex('users', 'users_mobile_index')) {
                $table->dropIndex('users_mobile_index');
            }

            if (! Schema::hasIndex('users', 'users_mobile_unique')) {
                $table->unique('mobile', 'users_mobile_unique');
            }
        });
    }
};
