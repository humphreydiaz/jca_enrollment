<?php

namespace Database\Seeders\Identity;

use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (RoleCode::cases() as $roleCode) {
            Role::query()->updateOrCreate(
                ['code' => $roleCode->value],
                [
                    'name' => ucfirst($roleCode->value),
                    'is_staff' => $roleCode->isStaff(),
                    'is_active' => true,
                ],
            );
        }
    }
}
