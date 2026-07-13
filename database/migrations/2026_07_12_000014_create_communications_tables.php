<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('title', 160);
            $table->text('body');
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('publish_until')->nullable()->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('announcement_audiences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->string('audience_type', 20);
            $table->string('role_code', 40)->nullable();
            $table->foreignId('section_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('guardian_profiles')->restrictOnDelete();
            $table->timestamps();
            $table->index(['announcement_id', 'audience_type']);
            $table->index(['role_code', 'audience_type']);
            $table->index(['section_id', 'audience_type']);
            $table->index(['student_id', 'audience_type']);
            $table->index(['guardian_id', 'audience_type']);
        });

        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 80);
            $table->string('channel', 20)->default('sms');
            $table->string('purpose', 40);
            $table->unsignedInteger('version')->default(1);
            $table->text('content');
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['code', 'channel', 'version']);
            $table->index(['code', 'channel', 'is_active']);
        });

        Schema::create('sms_consents', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('guardian_id')->constrained('guardian_profiles')->restrictOnDelete();
            $table->string('purpose', 40);
            $table->string('status', 20)->default('withdrawn')->index();
            $table->string('source', 60);
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['guardian_id', 'purpose']);
            $table->index(['guardian_id', 'purpose', 'status']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('notification_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recipient_guardian_id')->nullable()->constrained('guardian_profiles')->restrictOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('purpose', 40);
            $table->nullableMorphs('subject');
            $table->string('status', 20)->default('queued')->index();
            $table->string('idempotency_key', 160)->unique();
            $table->text('rendered_body')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['recipient_guardian_id', 'purpose', 'status']);
        });

        Schema::create('sms_messages', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('notification_id')->unique()->constrained()->restrictOnDelete();
            $table->string('provider', 30);
            $table->text('recipient_mobile');
            $table->char('recipient_hash', 64)->index();
            $table->string('recipient_last_four', 4)->nullable();
            $table->string('provider_reference', 120)->nullable()->index();
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('failure_code', 80)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'failed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('sms_consents');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('announcement_audiences');
        Schema::dropIfExists('announcements');
    }
};
