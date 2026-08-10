<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->foreignId('student_applicant_user_id')
                ->nullable()
                ->after('applicant_guardian_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('active_student_application_key', 80)
                ->nullable()
                ->unique()
                ->after('active_enrollment_key');
            $table->index(
                ['student_applicant_user_id', 'academic_year_id', 'status'],
                'enrollment_student_applicant_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropIndex('enrollment_student_applicant_status_index');
            $table->dropUnique(['active_student_application_key']);
            $table->dropConstrainedForeignId('student_applicant_user_id');
            $table->dropColumn('active_student_application_key');
        });
    }
};
