<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curricula', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('name', 150);
            $table->string('status', 20)->default('draft')->index();
            $table->string('source_note', 255)->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['academic_year_id', 'version']);
        });

        Schema::create('curriculum_grade_subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['curriculum_id', 'grade_level_id', 'subject_id'], 'curriculum_grade_subject_unique');
        });

        Schema::create('curriculum_section_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('capacity')->default(15);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('activity_schedule')->nullable();
            $table->timestamps();
            $table->unique(['curriculum_id', 'grade_level_id', 'name'], 'curriculum_section_name_unique');
        });

        Schema::create('curriculum_template_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_section_template_id');
            $table->foreign('curriculum_section_template_id', 'curr_template_schedule_template_fk')
                ->references('id')->on('curriculum_section_templates')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->text('schedule_text')->nullable();
            $table->timestamps();
            $table->unique(['curriculum_section_template_id', 'subject_id'], 'curriculum_template_subject_unique');
        });

        Schema::table('sections', function (Blueprint $table): void {
            $table->foreignId('curriculum_id')->nullable()->after('grade_level_id')->constrained()->nullOnDelete();
            $table->text('activity_schedule')->nullable()->after('name');
        });

        Schema::table('section_subjects', function (Blueprint $table): void {
            $table->foreignId('curriculum_id')->nullable()->after('subject_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('section_subjects', fn (Blueprint $table) => $table->dropConstrainedForeignId('curriculum_id'));
        Schema::table('sections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('curriculum_id');
            $table->dropColumn('activity_schedule');
        });
        Schema::dropIfExists('curriculum_template_schedules');
        Schema::dropIfExists('curriculum_section_templates');
        Schema::dropIfExists('curriculum_grade_subjects');
        Schema::dropIfExists('curricula');
    }
};
