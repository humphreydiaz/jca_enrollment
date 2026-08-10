<?php

namespace Database\Seeders;

use Database\Seeders\Academics\GradeLevelSeeder;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(GradeLevelSeeder::class);
    }
}
