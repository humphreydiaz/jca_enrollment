<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollment_requirements', function (Blueprint $table): void {
            $table->foreignId('carried_from_requirement_id')->nullable()->after('document_type_id')->constrained('enrollment_requirements')->restrictOnDelete();
            $table->foreignId('carried_over_by')->nullable()->after('carried_from_requirement_id')->constrained('users')->nullOnDelete();
            $table->timestamp('carried_over_at')->nullable()->after('carried_over_by');
        });
    }

    public function down(): void
    {
        Schema::table('enrollment_requirements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('carried_over_by');
            $table->dropConstrainedForeignId('carried_from_requirement_id');
            $table->dropColumn('carried_over_at');
        });
    }
};
