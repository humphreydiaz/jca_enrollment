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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->boolean('is_staff')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->string('active_assignment_key', 80)->nullable()->unique();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
            $table->index(['role_id', 'revoked_at']);
        });

        Schema::create('account_invitations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('purpose', 40)->default('account_setup');
            $table->string('channel', 20);
            $table->string('sent_to', 255);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_invitations');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
    }
};
