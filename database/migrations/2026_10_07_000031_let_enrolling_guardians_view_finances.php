<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D-053: parents/guardians who were verified to manage a learner's enrollment (walk-in access, sibling links,
 * legacy identity connection) were never allowed to see that learner's fees, so the portal's Financial records
 * page stayed empty for every family. Grant the financial view to those verified, current links.
 * Irreversible on purpose: rolling back would hide fees from families again; down() is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('guardian_student')
            ->where('may_manage_enrollment', true)
            ->where('may_view_financials', false)
            ->whereNotNull('verified_at')
            ->whereNull('ended_at')
            ->update(['may_view_financials' => true, 'updated_at' => now()]);
    }

    public function down(): void {}
};
