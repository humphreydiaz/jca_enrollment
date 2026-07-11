<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('guardian_profiles', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('preferred_contact_method', 20)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('student_number', 50)->nullable()->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('preferred_name', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained('guardian_profiles')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('student_profiles')->restrictOnDelete();
            $table->string('relationship_type', 50);
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('may_receive_sms')->default(true);
            $table->boolean('may_view_financials')->default(false);
            $table->boolean('may_manage_enrollment')->default(true);
            $table->boolean('has_legal_authority')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['guardian_id', 'student_id']);
            $table->index(['student_id', 'ended_at']);
        });

        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('employee_number', 50)->nullable()->unique();
            $table->string('position_title', 100)->nullable();
            $table->date('active_from')->nullable();
            $table->date('active_until')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('student_profiles');
        Schema::dropIfExists('guardian_profiles');
    }
};
