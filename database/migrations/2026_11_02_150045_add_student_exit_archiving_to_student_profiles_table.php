<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamp('graduated_at')->nullable();
            $table->timestamp('transferred_out_at')->nullable();
            $table->text('archive_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);

            $table->dropColumn([
                'archived_at',
                'graduated_at',
                'transferred_out_at',
                'archive_reason',
            ]);
        });
    }
};