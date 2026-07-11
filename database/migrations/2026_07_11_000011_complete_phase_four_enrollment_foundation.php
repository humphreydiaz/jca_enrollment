<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->string('resume_token_hash', 64)->nullable()->unique()->after('active_enrollment_key');
            $table->string('verification_code_hash', 64)->nullable()->after('resume_token_hash');
            $table->timestamp('verification_expires_at')->nullable()->after('verification_code_hash');
            $table->unsignedTinyInteger('verification_attempts')->default(0)->after('verification_expires_at');
            $table->string('guardian_relationship', 50)->nullable()->after('student_category');
            $table->string('previous_school_name')->nullable()->after('guardian_relationship');
            $table->string('duplicate_review_status', 30)->default('clear')->index()->after('previous_school_name');
            $table->foreignId('potential_duplicate_student_id')->nullable()->after('duplicate_review_status')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('potential_duplicate_guardian_id')->nullable()->after('potential_duplicate_student_id')->constrained('guardian_profiles')->restrictOnDelete();
            $table->foreignId('duplicate_reviewed_by')->nullable()->after('potential_duplicate_guardian_id')->constrained('users')->nullOnDelete();
            $table->timestamp('duplicate_reviewed_at')->nullable()->after('duplicate_reviewed_by');
            $table->text('duplicate_review_reason')->nullable()->after('duplicate_reviewed_at');
        });

        Schema::table('support_cases', function (Blueprint $table): void {
            $table->index(['assigned_reviewer_id', 'status'], 'support_reviewer_status_index');
        });

        Schema::table('document_access_logs', function (Blueprint $table): void {
            $table->index(['document_upload_id', 'occurred_at'], 'document_access_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('document_access_logs', function (Blueprint $table): void {
            $table->index('document_upload_id', 'document_access_logs_document_upload_id_foreign');
            $table->dropIndex('document_access_lookup_index');
        });
        Schema::table('support_cases', function (Blueprint $table): void {
            $table->index('assigned_reviewer_id', 'support_cases_assigned_reviewer_id_foreign');
            $table->dropIndex('support_reviewer_status_index');
        });
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('duplicate_reviewed_by');
            $table->dropConstrainedForeignId('potential_duplicate_guardian_id');
            $table->dropConstrainedForeignId('potential_duplicate_student_id');
            $table->dropUnique(['resume_token_hash']);
            $table->dropIndex(['duplicate_review_status']);
            $table->dropColumn([
                'resume_token_hash', 'verification_code_hash', 'verification_expires_at',
                'verification_attempts', 'guardian_relationship', 'previous_school_name',
                'duplicate_review_status', 'duplicate_reviewed_at', 'duplicate_review_reason',
            ]);
        });
    }
};
