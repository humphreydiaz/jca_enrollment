<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-052: Communications without SMS templates.
 *
 * - An announcement says how it is delivered (portal, sms, or both) and carries its own SMS text.
 * - Admin "message people" sends are grouped as one direct message for the sent history.
 * - Any portal person can hold an SMS opt-in (parent, learner, or staff account), and a learner
 *   may have an optional mobile number from the application form.
 * - A queued SMS can point at a learner record, not only a parent or an account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->string('delivery', 12)->default('portal')->after('body');
            $table->text('sms_body')->nullable()->after('delivery');
        });

        Schema::create('direct_messages', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->text('body');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->foreignId('sent_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->string('mobile', 30)->nullable()->after('permanent_address');
        });

        Schema::table('sms_consents', function (Blueprint $table): void {
            $table->foreignId('guardian_id')->nullable()->change();
            $table->foreignId('user_id')->nullable()->after('guardian_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->after('user_id')->constrained('student_profiles')->restrictOnDelete();
            $table->unique(['user_id', 'purpose']);
            $table->unique(['student_id', 'purpose']);
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->foreignId('recipient_student_id')->nullable()->after('recipient_user_id')->constrained('student_profiles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recipient_student_id');
        });
        // Foreign keys first: MySQL will not drop a unique index a foreign key still relies on.
        // guardian_id stays nullable; learner/staff opt-ins would otherwise block the rollback.
        Schema::table('sms_consents', function (Blueprint $table): void {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['user_id']);
        });
        Schema::table('sms_consents', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'purpose']);
            $table->dropUnique(['student_id', 'purpose']);
            $table->dropColumn(['student_id', 'user_id']);
        });
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->dropColumn('mobile');
        });
        Schema::dropIfExists('direct_messages');
        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropColumn(['delivery', 'sms_body']);
        });
    }
};
