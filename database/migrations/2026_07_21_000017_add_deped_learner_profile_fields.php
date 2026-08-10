<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->string('lrn', 12)->nullable()->after('student_number')->index();
            $table->string('extension_name', 30)->nullable()->after('middle_name');
            $table->string('sex', 20)->nullable()->after('preferred_name');
            $table->string('birth_place')->nullable()->after('birth_date');
            $table->string('mother_tongue', 100)->nullable()->after('birth_place');
            $table->string('religion', 100)->nullable()->after('mother_tongue');
            $table->string('ip_group', 150)->nullable()->after('religion');
            $table->boolean('is_4ps_recipient')->default(false)->after('ip_group');
            $table->string('four_ps_household_id', 50)->nullable()->after('is_4ps_recipient');
            $table->boolean('is_learner_with_disability')->default(false)->after('four_ps_household_id');
            $table->string('disability_type', 100)->nullable()->after('is_learner_with_disability');
            $table->text('current_address')->nullable()->after('disability_type');
            $table->text('permanent_address')->nullable()->after('current_address');
            $table->string('father_name')->nullable()->after('permanent_address');
            $table->string('mother_maiden_name')->nullable()->after('father_name');
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->string('previous_school_address')->nullable()->after('previous_school_name');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropColumn('previous_school_address');
        });

        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->dropIndex(['lrn']);
            $table->dropColumn([
                'lrn',
                'extension_name',
                'sex',
                'birth_place',
                'mother_tongue',
                'religion',
                'ip_group',
                'is_4ps_recipient',
                'four_ps_household_id',
                'is_learner_with_disability',
                'disability_type',
                'current_address',
                'permanent_address',
                'father_name',
                'mother_maiden_name',
            ]);
        });
    }
};
