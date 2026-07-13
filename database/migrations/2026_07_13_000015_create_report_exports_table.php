<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('report_code', 40);
            $table->json('filter_snapshot')->nullable();
            $table->json('field_snapshot');
            $table->string('status', 20)->default('queued')->index();
            $table->string('disk', 60);
            $table->string('storage_path', 500)->nullable()->unique();
            $table->string('download_name', 180);
            $table->unsignedInteger('row_count')->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['requested_by', 'created_at']);
            $table->index(['report_code', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
