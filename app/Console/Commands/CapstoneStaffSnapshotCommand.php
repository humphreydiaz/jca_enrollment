<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\Capstone\CapstoneDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Before rebuilding the capstone database: remember who the Admin, Registrar, and Accounting
 * accounts are (name, email, mobile, Staff ID), so CapstoneDemoSeeder recreates the same people.
 * Passwords are not copied. The seeder deletes the snapshot once it has used it.
 */
class CapstoneStaffSnapshotCommand extends Command
{
    /** @var string */
    protected $signature = 'capstone:snapshot-staff';

    /** @var string */
    protected $description = 'Remember the Admin, Registrar, and Accounting accounts before rebuilding the capstone demonstration data';

    public function handle(): int
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('user_roles')) {
            $this->error('There are no accounts in this database to remember.');

            return self::FAILURE;
        }

        $accounts = User::query()
            ->with(['roles', 'staffProfile'])
            ->whereHas('roles', fn ($roles) => $roles->whereIn('code', ['admin', 'registrar', 'accounting']))
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => [
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'employee_number' => $user->staffProfile?->employee_number,
                'position_title' => $user->staffProfile?->position_title,
                'roles' => $user->roles->pluck('code')->intersect(['admin', 'registrar', 'accounting'])->values()->all(),
            ])->values();

        if ($accounts->isEmpty()) {
            $this->error('No active Admin, Registrar, or Accounting account was found.');

            return self::FAILURE;
        }

        Storage::disk('local')->put(CapstoneDemoSeeder::SNAPSHOT_PATH, json_encode(['taken_at' => now()->toIso8601String(), 'accounts' => $accounts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->table(['Roles', 'Name', 'Staff ID', 'Email', 'Mobile'], $accounts->map(fn (array $account) => [
            implode(', ', $account['roles']), $account['name'], $account['employee_number'] ?? '—', $account['email'] ?? '—', $account['mobile'] ? 'on file' : '—',
        ])->all());
        $this->info('Saved. These accounts will be recreated by the capstone seeder with the password '.CapstoneDemoSeeder::PASSWORD.'.');

        return self::SUCCESS;
    }
}
