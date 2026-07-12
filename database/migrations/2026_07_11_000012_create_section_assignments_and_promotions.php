<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->timestamp('enrolled_at')->nullable()->after('approved_at');
            $table->timestamp('withdrawn_at')->nullable()->after('rejected_at');
            $table->unique('previous_enrollment_id', 'enrollments_previous_unique');
        });

        Schema::create('section_assignments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('confirmed')->index();
            $table->string('active_assignment_key', 40)->nullable()->unique();
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at');
            $table->date('effective_from');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('ended_reason')->nullable();
            $table->foreignId('transferred_from_id')->nullable()->constrained('section_assignments')->restrictOnDelete();
            $table->boolean('support_suitability_confirmed')->default(false);
            $table->foreignId('suitability_confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('suitability_confirmed_at')->nullable();
            $table->boolean('is_capacity_override')->default(false);
            $table->text('capacity_override_reason')->nullable();
            $table->foreignId('capacity_override_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['section_id', 'status', 'ended_at'], 'section_assignment_capacity_idx');
            $table->index(['enrollment_id', 'status', 'ended_at'], 'section_assignment_current_idx');
        });

        Schema::create('section_assignment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 30);
            $table->foreignId('from_section_id')->nullable()->constrained('sections')->restrictOnDelete();
            $table->foreignId('to_section_id')->nullable()->constrained('sections')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->boolean('is_capacity_override')->default(false);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['enrollment_id', 'occurred_at'], 'section_assignment_event_history_idx');
        });

        Schema::create('promotion_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('source_enrollment_id')->unique()->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('target_academic_year_id')->nullable()->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('target_grade_level_id')->nullable()->constrained('grade_levels')->restrictOnDelete();
            $table->string('eligibility_status', 20)->index();
            $table->text('reason')->nullable();
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at');
            $table->foreignId('created_enrollment_id')->nullable()->unique()->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['target_academic_year_id', 'eligibility_status'], 'promotion_target_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_recommendations');
        Schema::dropIfExists('section_assignment_events');
        Schema::dropIfExists('section_assignments');

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->index('previous_enrollment_id', 'enrollments_previous_enrollment_id_foreign');
            $table->dropUnique('enrollments_previous_unique');
            $table->dropColumn(['enrolled_at', 'withdrawn_at']);
        });
    }
};
