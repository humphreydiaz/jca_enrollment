<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollment_requirements', function (Blueprint $table): void {
            $table->string('soft_copy_status', 30)->default('not_uploaded')->after('receipt_notes')->index();
            $table->foreignId('soft_copy_reviewed_by')->nullable()->after('soft_copy_status')->constrained('users')->nullOnDelete();
            $table->timestamp('soft_copy_reviewed_at')->nullable()->after('soft_copy_reviewed_by');
            $table->text('soft_copy_review_reason')->nullable()->after('soft_copy_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('enrollment_requirements', function (Blueprint $table): void {
            $table->dropIndex(['soft_copy_status']);
            $table->dropConstrainedForeignId('soft_copy_reviewed_by');
            $table->dropColumn(['soft_copy_status', 'soft_copy_reviewed_at', 'soft_copy_review_reason']);
        });
    }
};
