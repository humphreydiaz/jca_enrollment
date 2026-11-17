<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('academic_years', 'enrollment_is_open')) {
            Schema::table('academic_years', function (Blueprint $table) {
                $table->boolean('enrollment_is_open')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('academic_years', 'enrollment_is_open')) {
            Schema::table('academic_years', function (Blueprint $table) {
                $table->dropColumn('enrollment_is_open');
            });
        }
    }
};