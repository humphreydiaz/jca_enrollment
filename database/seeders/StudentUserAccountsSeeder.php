<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a Student-role login account for every student profile that has none.
 * Safe to re-run: only processes students where user_id IS NULL.
 */
class StudentUserAccountsSeeder extends Seeder
{
    private const PASSWORD = 'Password123!';

    /** @var array<string,bool> */
    private array $usedEmails = [];

    public function run(): void
    {
        $admin = User::query()->whereHas('roles', fn ($q) => $q->where('code', 'admin'))->first() ?? User::query()->firstOrFail();

        // Seed the used-email set with existing emails to guarantee uniqueness.
        foreach (User::query()->pluck('email')->filter() as $existing) {
            $this->usedEmails[Str::lower($existing)] = true;
        }

        $students = StudentProfile::query()->whereNull('user_id')->orderBy('id')->get();
        $created = 0;

        DB::transaction(function () use ($students, $admin, &$created): void {
            foreach ($students as $student) {
                $email = $this->uniqueEmail($student->first_name, $student->last_name);
                $user = User::query()->create([
                    'name' => trim($student->first_name.' '.$student->last_name),
                    'email' => $email,
                    'password' => self::PASSWORD,
                    'status' => 'active',
                    'must_change_password' => false,
                    'password_changed_at' => now(),
                    'email_verified_at' => now(),
                ]);
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => 6, // student
                    'active_assignment_key' => $user->id.':6',
                    'assigned_by' => $admin->id,
                    'assigned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $student->update(['user_id' => $user->id]);
                $created++;
            }
        });

        $this->command->info("StudentUserAccountsSeeder: created {$created} student accounts. Shared password: ".self::PASSWORD);
    }

    private function uniqueEmail(string $first, string $surname): string
    {
        $base = Str::of($first.'.'.$surname)->lower()->replace(' ', '')->ascii()->toString();
        $email = $base.'@student.jca.test';
        $n = 1;
        while (isset($this->usedEmails[Str::lower($email)])) {
            $email = $base.(++$n).'@student.jca.test';
        }
        $this->usedEmails[Str::lower($email)] = true;

        return $email;
    }
}
