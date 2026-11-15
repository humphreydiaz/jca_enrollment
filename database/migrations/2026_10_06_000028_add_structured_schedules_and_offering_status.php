<?php

use App\Modules\Academics\Support\ClassSchedule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Structured class meetings (weekdays + start/end time) for section offerings and curriculum
 * timetable templates, plus an archive status for section offerings. Existing free-text
 * schedules in the recognized "Monday-Friday HH:MM-HH:MM" style are backfilled; any other note
 * is left untouched in schedule_text. Rolling back drops only the added columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('section_subjects', function (Blueprint $table): void {
            $table->string('status', 20)->default('active')->after('curriculum_id')->index();
            $table->string('days', 20)->nullable()->after('status');
            $table->time('starts_at')->nullable()->after('days');
            $table->time('ends_at')->nullable()->after('starts_at');
        });

        Schema::table('curriculum_template_schedules', function (Blueprint $table): void {
            $table->string('days', 20)->nullable()->after('subject_id');
            $table->time('starts_at')->nullable()->after('days');
            $table->time('ends_at')->nullable()->after('starts_at');
        });

        foreach (['section_subjects', 'curriculum_template_schedules'] as $tableName) {
            DB::table($tableName)->whereNotNull('schedule_text')->orderBy('id')->chunkById(200, function ($rows) use ($tableName): void {
                foreach ($rows as $row) {
                    try {
                        $schedule = ClassSchedule::parse($row->schedule_text);
                    } catch (ValidationException) {
                        $schedule = null;
                    }
                    if ($schedule !== null) {
                        $attributes = $schedule->attributes();
                        DB::table($tableName)->where('id', $row->id)->update([
                            'days' => $attributes['days'],
                            'starts_at' => $attributes['starts_at'],
                            'ends_at' => $attributes['ends_at'],
                        ]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('curriculum_template_schedules', function (Blueprint $table): void {
            $table->dropColumn(['days', 'starts_at', 'ends_at']);
        });
        Schema::table('section_subjects', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'days', 'starts_at', 'ends_at']);
        });
    }
};
