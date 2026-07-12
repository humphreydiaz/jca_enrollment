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
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('public_id')->unique()->after('id');
            $table->string('email')->nullable()->change();
            $table->string('mobile', 30)->nullable()->unique()->after('email');
            $table->timestamp('mobile_verified_at')->nullable()->after('email_verified_at');
            $table->string('password')->nullable()->change();
            $table->string('status', 20)->default('invited')->index()->after('password');
            $table->boolean('must_change_password')->default(false)->after('status');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            $table->timestamp('last_login_at')->nullable()->after('password_changed_at');
            $table->timestamp('locked_at')->nullable()->after('last_login_at');
            $table->timestamp('disabled_at')->nullable()->after('locked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_public_id_unique');
            $table->dropUnique('users_mobile_unique');
            $table->dropIndex('users_status_index');
            $table->dropColumn([
                'public_id',
                'mobile',
                'mobile_verified_at',
                'status',
                'must_change_password',
                'password_changed_at',
                'last_login_at',
                'locked_at',
                'disabled_at',
            ]);
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
