<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('enrollment_opens_on')->nullable();
            $table->date('enrollment_closes_on')->nullable();
            $table->string('status', 20)->default('planned')->index();
            $table->timestamps();
        });

        Schema::create('grade_levels', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sequence')->unique();
            $table->string('school_stage', 50)->nullable();
            $table->foreignId('next_grade_level_id')->nullable()->constrained('grade_levels')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('sections', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('capacity')->default(15);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->unique(['academic_year_id', 'grade_level_id', 'name']);
        });

        Schema::create('section_subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->text('schedule_text')->nullable();
            $table->timestamps();

            $table->unique(['section_id', 'subject_id']);
        });

        Schema::create('teaching_assignments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('assignment_type', 30);
            $table->date('active_from')->nullable();
            $table->date('active_until')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['staff_profile_id', 'is_active', 'active_until'], 'teaching_assignment_staff_active_idx');
            $table->index(['section_id', 'is_active'], 'teaching_assignment_section_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_assignments');
        Schema::dropIfExists('section_subjects');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('grade_levels');
        Schema::dropIfExists('academic_years');
    }
};
