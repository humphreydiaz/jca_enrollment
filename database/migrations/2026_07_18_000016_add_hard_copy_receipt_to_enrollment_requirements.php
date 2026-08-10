<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollment_requirements', function (Blueprint $table): void {
            $table->foreignId('received_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable()->after('received_by');
            $table->text('receipt_notes')->nullable()->after('received_at');
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::table('enrollment_requirements', function (Blueprint $table): void {
            $table->dropIndex(['status', 'received_at']);
            $table->dropConstrainedForeignId('received_by');
            $table->dropColumn(['received_at', 'receipt_notes']);
        });
    }
};
