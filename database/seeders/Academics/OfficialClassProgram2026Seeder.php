<?php

namespace Database\Seeders\Academics;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\Curriculum;
use App\Modules\Academics\Support\OfficialClassProgramTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Prepare the owner-supplied 2026-2027 class program for Admin review. */
class OfficialClassProgram2026Seeder extends Seeder
{
    public function run(): void
    {
        $this->call(GradeLevelSeeder::class);

        DB::transaction(function (): void {
            $year = AcademicYear::query()->firstOrCreate(
                ['code' => '2026-2027'],
                ['name' => 'School Year 2026-2027', 'starts_on' => '2026-06-08', 'ends_on' => '2027-04-08', 'status' => 'planned'],
            );
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($year->id);
            if (Curriculum::query()->where('academic_year_id', $year->id)->where('name', 'JCA 2026-2027 official class-program draft')->exists()) {
                return;
            }

            $version = (int) Curriculum::query()->where('academic_year_id', $year->id)->max('version') + 1;
            $curriculum = Curriculum::query()->create([
                'academic_year_id' => $year->id,
                'version' => $version,
                'name' => 'JCA 2026-2027 official class-program draft',
                'status' => 'draft',
                'source_note' => 'Owner-supplied class-program-26-27 official.pdf, all 35 pages; review schedules and staff before publishing.',
            ]);

            app(OfficialClassProgramTemplate::class)->populate($curriculum);
        });

        $this->command?->info('Official class-program draft prepared for Admin review. No teacher account or teaching assignment was changed.');
    }
}
