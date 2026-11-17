<?php

namespace Database\Seeders\Requirements;

use Illuminate\Database\Seeder;
use RuntimeException;

class RequirementsDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('The requirements development seeder may run only in the local environment.');
        }

        $this->call(RequirementsBaselineSeeder::class);
    }
}
