<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-066: the retention report needs exits as data, not only as the archive_reason text.
 *
 * - exit_category: why a learner left (dropped_out, excluded, other_drop, transferred_out).
 * - dropped_at: the drop's effective date (transfers already have transferred_out_at).
 * - exit_receiving_school: the school a transferee went to.
 *
 * Existing rows are backfilled from archive_reason where it is unambiguous; a drop recorded before
 * this change gets no category (shown as "Reason not categorised"). down() drops the columns only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('exit_category', 40)->nullable()->after('status')->index();
            $table->date('dropped_at')->nullable()->after('transferred_out_at');
            $table->string('exit_receiving_school', 255)->nullable()->after('dropped_at');
        });

        DB::table('student_profiles')->where('status', 'transferred_out')->orderBy('id')->each(function ($row): void {
            $school = preg_match('/^Transferred out to: (.+?)(?: \(School ID [^)]*\))?$/m', (string) $row->archive_reason, $match) ? trim($match[1]) : null;
            DB::table('student_profiles')->where('id', $row->id)->update([
                'exit_category' => 'transferred_out',
                'exit_receiving_school' => $school !== '' ? $school : null,
            ]);
        });

        DB::table('student_profiles')->where('status', 'dropped')->orderBy('id')->each(function ($row): void {
            $date = preg_match('/^Dropped effective: (.+)$/m', (string) $row->archive_reason, $match) ? strtotime($match[1]) : false;
            DB::table('student_profiles')->where('id', $row->id)->update([
                'dropped_at' => $date ? date('Y-m-d', $date) : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropIndex(['exit_category']);
            $table->dropColumn(['exit_category', 'dropped_at', 'exit_receiving_school']);
        });
    }
};
