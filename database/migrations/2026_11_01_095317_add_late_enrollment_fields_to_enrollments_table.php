<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->boolean('is_late_enrollment')
                ->default(false);

            $table->string('late_enrollment_status')
                ->nullable();

            $table->foreignId('late_enrollment_approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('late_enrollment_approved_at')
                ->nullable();

            $table->text('late_enrollment_reason')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign([
                'late_enrollment_approved_by'
            ]);

            $table->dropColumn([
                'is_late_enrollment',
                'late_enrollment_status',
                'late_enrollment_approved_by',
                'late_enrollment_approved_at',
                'late_enrollment_reason',
            ]);
        });
    }
};
