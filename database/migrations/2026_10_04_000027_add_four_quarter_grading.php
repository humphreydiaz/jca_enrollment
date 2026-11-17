<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('quarter_number');
            $table->string('name', 30);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->unique(['academic_year_id', 'quarter_number']);
        });

        Schema::create('student_subject_grades', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('teaching_assignment_id')->constrained()->restrictOnDelete();
            $table->string('subject_code_snapshot', 30);
            $table->string('subject_name_snapshot');
            $table->unsignedTinyInteger('quarter_1')->nullable();
            $table->unsignedTinyInteger('quarter_2')->nullable();
            $table->unsignedTinyInteger('quarter_3')->nullable();
            $table->unsignedTinyInteger('quarter_4')->nullable();
            $table->unsignedTinyInteger('final_grade')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['enrollment_id', 'subject_id'], 'student_subject_grade_unique');
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('grade_correction_requests', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('student_subject_grade_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('previous_status', 20);
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('grade_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_subject_grade_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 30);
            $table->unsignedTinyInteger('quarter_number')->nullable();
            $table->unsignedTinyInteger('old_value')->nullable();
            $table->unsignedTinyInteger('new_value')->nullable();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['student_subject_grade_id', 'occurred_at'], 'grade_revision_history_idx');
        });

        DB::statement('ALTER TABLE grading_periods ADD CONSTRAINT grading_period_quarter_check CHECK (quarter_number BETWEEN 1 AND 4)');
        DB::statement('ALTER TABLE student_subject_grades ADD CONSTRAINT student_grade_values_check CHECK (
            (quarter_1 IS NULL OR quarter_1 <= 100) AND (quarter_2 IS NULL OR quarter_2 <= 100)
            AND (quarter_3 IS NULL OR quarter_3 <= 100) AND (quarter_4 IS NULL OR quarter_4 <= 100)
            AND (final_grade IS NULL OR final_grade <= 100)
            AND (((quarter_1 IS NULL OR quarter_2 IS NULL OR quarter_3 IS NULL OR quarter_4 IS NULL) AND final_grade IS NULL)
                OR (quarter_1 IS NOT NULL AND quarter_2 IS NOT NULL AND quarter_3 IS NOT NULL AND quarter_4 IS NOT NULL
                    AND final_grade = FLOOR((quarter_1 + quarter_2 + quarter_3 + quarter_4 + 2) / 4)))
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_revisions');
        Schema::dropIfExists('grade_correction_requests');
        Schema::dropIfExists('student_subject_grades');
        Schema::dropIfExists('grading_periods');
    }
};
