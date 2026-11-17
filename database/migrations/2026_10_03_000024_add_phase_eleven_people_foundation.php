<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identifier_namespaces', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier_type', 20);
            $table->char('year_code', 2);
            $table->timestamps();

            $table->unique(['identifier_type', 'year_code']);
        });

        Schema::table('guardian_profiles', function (Blueprint $table): void {
            $table->string('extension_name', 30)->nullable()->after('middle_name');
        });

        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->string('father_first_name', 100)->nullable()->after('permanent_address');
            $table->string('father_middle_name', 100)->nullable()->after('father_first_name');
            $table->string('father_last_name', 100)->nullable()->after('father_middle_name');
            $table->string('father_extension_name', 30)->nullable()->after('father_last_name');
            $table->string('mother_first_name', 100)->nullable()->after('father_extension_name');
            $table->string('mother_middle_name', 100)->nullable()->after('mother_first_name');
            $table->string('mother_maiden_last_name', 100)->nullable()->after('mother_middle_name');
            $table->string('mother_extension_name', 30)->nullable()->after('mother_maiden_last_name');

            $table->dropIndex(['lrn']);
            $table->unique('lrn');
        });

        Schema::create('student_profile_corrections', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('student_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('field_name', 80);
            $table->text('before_value')->nullable();
            $table->text('after_value')->nullable();
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'created_at']);
        });

        Schema::create('enrollment_corrections', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('field_name', 80);
            $table->text('before_value')->nullable();
            $table->text('after_value')->nullable();
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['enrollment_id', 'created_at']);
        });

        Schema::dropIfExists('student_number_counters');

    }

    public function down(): void
    {
        if (! Schema::hasTable('student_number_counters')) {
            Schema::create('student_number_counters', function (Blueprint $table): void {
                $table->string('year_prefix', 9)->primary();
                $table->unsignedInteger('last_sequence')->default(0);
                $table->timestamps();
            });
        }

        Schema::dropIfExists('enrollment_corrections');
        Schema::dropIfExists('student_profile_corrections');

        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->dropUnique(['lrn']);
            $table->index('lrn');
            $table->dropColumn([
                'father_first_name',
                'father_middle_name',
                'father_last_name',
                'father_extension_name',
                'mother_first_name',
                'mother_middle_name',
                'mother_maiden_last_name',
                'mother_extension_name',
            ]);
        });

        Schema::table('guardian_profiles', function (Blueprint $table): void {
            $table->dropColumn('extension_name');
        });

        Schema::dropIfExists('identifier_namespaces');
    }
};
