<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('temporary_access_expires_at')
                ->nullable()
                ->after('password_changed_at')
                ->index();
            $table->timestamp('temporary_access_used_at')
                ->nullable()
                ->after('temporary_access_expires_at');
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->foreignId('access_issued_to_user_id')
                ->nullable()
                ->after('student_applicant_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('walk_in_verified_by')
                ->nullable()
                ->after('access_issued_to_user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('walk_in_verified_at')->nullable()->after('walk_in_verified_by');
            $table->timestamp('access_issued_at')->nullable()->after('walk_in_verified_at');

            $table->index(
                ['access_issued_to_user_id', 'status'],
                'enrollment_access_owner_status_index',
            );
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('enrollments', 'access_issued_to_user_id')) {
            Schema::table('enrollments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('walk_in_verified_by');
                $table->dropConstrainedForeignId('access_issued_to_user_id');
                $table->dropIndex('enrollment_access_owner_status_index');
                $table->dropColumn(['walk_in_verified_at', 'access_issued_at']);
            });
        }

        if (Schema::hasColumn('users', 'temporary_access_expires_at')) {
            $columns = ['temporary_access_expires_at'];
            $hasExpiryIndex = collect(Schema::getIndexes('users'))
                ->contains(fn (array $index): bool => $index['name'] === 'users_temporary_access_expires_at_index');
            if (Schema::hasColumn('users', 'temporary_access_used_at')) {
                $columns[] = 'temporary_access_used_at';
            }

            Schema::table('users', function (Blueprint $table) use ($columns, $hasExpiryIndex): void {
                if ($hasExpiryIndex) {
                    $table->dropIndex(['temporary_access_expires_at']);
                }
                $table->dropColumn($columns);
            });
        }
    }
};
