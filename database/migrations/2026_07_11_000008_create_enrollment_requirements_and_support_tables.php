<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('application_number', 40)->unique();
            $table->foreignId('student_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('applicant_guardian_id')->nullable()->constrained('guardian_profiles')->nullOnDelete();
            $table->string('student_category', 30);
            $table->string('status', 30)->default('draft')->index();
            $table->string('active_enrollment_key', 80)->nullable()->unique();
            $table->timestamp('contact_verified_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->foreignId('previous_enrollment_id')->nullable()->constrained('enrollments')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['academic_year_id', 'status']);
            $table->index(['applicant_guardian_id', 'status']);
        });
        Schema::create('enrollment_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();
            $table->index(['enrollment_id', 'changed_at']);
        });
        Schema::create('enrollment_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->string('visibility_scope', 30)->default('registrar');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('enrollment_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->string('decision_type', 30);
            $table->string('outcome', 30);
            $table->text('reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();
        });
        Schema::create('document_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('sensitivity_class', 30)->default('standard');
            $table->string('retention_policy_key', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('requirement_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('student_category', 30);
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_blocking')->default(true);
            $table->text('notes')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();
            $table->unique(['academic_year_id', 'student_category', 'document_type_id'], 'requirement_rule_unique');
        });
        Schema::create('enrollment_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->json('rule_snapshot');
            $table->string('status', 30)->default('missing')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['enrollment_id', 'document_type_id']);
        });
        Schema::create('document_uploads', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('enrollment_requirement_id')->constrained()->restrictOnDelete();
            $table->string('disk', 40);
            $table->string('path', 255)->unique();
            $table->string('original_filename', 255);
            $table->string('stored_filename', 255);
            $table->string('mime_type', 100);
            $table->string('extension', 20);
            $table->unsignedBigInteger('byte_size');
            $table->string('checksum', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('validation_status', 30)->default('accepted');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['enrollment_requirement_id', 'version']);
        });
        Schema::create('document_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_upload_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->string('purpose', 100)->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();
        });
        Schema::create('support_cases', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('enrollment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('open')->index();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->useCurrent();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('final_outcome', 40)->nullable();
            $table->timestamps();
        });
        Schema::create('support_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_case_id')->constrained()->restrictOnDelete();
            $table->string('assessment_type', 60)->default('school_review');
            $table->date('completed_on')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('outcome', 40)->default('pending');
            $table->text('confidential_rationale')->nullable();
            $table->string('next_action', 100)->nullable();
            $table->timestamps();
        });
        Schema::create('support_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_case_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->string('category', 60)->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('observed_at')->useCurrent();
            $table->timestamps();
        });
        Schema::create('approved_support_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_case_id')->constrained()->restrictOnDelete();
            $table->text('summary');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained('approved_support_summaries')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('support_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_case_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('purpose', 100)->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['support_access_logs', 'approved_support_summaries', 'support_observations', 'support_assessments', 'support_cases', 'document_access_logs', 'document_uploads', 'enrollment_requirements', 'requirement_rules', 'document_types', 'enrollment_decisions', 'enrollment_notes', 'enrollment_status_history', 'enrollments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
