<?php

namespace Database\Seeders\Academics;

use App\Modules\Academics\Models\GradeLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class GradeLevelSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = collect([
            ['code' => 'NUR', 'name' => 'Nursery', 'sequence' => 1, 'school_stage' => 'Pre-elementary', 'next' => 'JK'],
            ['code' => 'JK', 'name' => 'Junior Kinder', 'sequence' => 2, 'school_stage' => 'Pre-elementary', 'next' => 'SK'],
            ['code' => 'SK', 'name' => 'Senior Kinder', 'sequence' => 3, 'school_stage' => 'Pre-elementary', 'next' => 'G1'],
            ['code' => 'G1', 'name' => 'Grade 1', 'sequence' => 4, 'school_stage' => 'Elementary', 'next' => 'G2'],
            ['code' => 'G2', 'name' => 'Grade 2', 'sequence' => 5, 'school_stage' => 'Elementary', 'next' => 'G3'],
            ['code' => 'G3', 'name' => 'Grade 3', 'sequence' => 6, 'school_stage' => 'Elementary', 'next' => 'G4'],
            ['code' => 'G4', 'name' => 'Grade 4', 'sequence' => 7, 'school_stage' => 'Elementary', 'next' => 'G5'],
            ['code' => 'G5', 'name' => 'Grade 5', 'sequence' => 8, 'school_stage' => 'Elementary', 'next' => 'G6'],
            ['code' => 'G6', 'name' => 'Grade 6', 'sequence' => 9, 'school_stage' => 'Elementary', 'next' => 'G7'],
            ['code' => 'G7', 'name' => 'Grade 7', 'sequence' => 10, 'school_stage' => 'Junior High School', 'next' => 'G8'],
            ['code' => 'G8', 'name' => 'Grade 8', 'sequence' => 11, 'school_stage' => 'Junior High School', 'next' => 'G9'],
            ['code' => 'G9', 'name' => 'Grade 9', 'sequence' => 12, 'school_stage' => 'Junior High School', 'next' => 'G10'],
            // JCA does not offer Senior High School, so Grade 10 completes the
            // progression and has no next grade.
            ['code' => 'G10', 'name' => 'Grade 10', 'sequence' => 13, 'school_stage' => 'Junior High School', 'next' => null],
        ]);

        DB::transaction(function () use ($catalog): void {
            $grades = GradeLevel::query()->lockForUpdate()->get();
            $catalogCodes = $catalog->pluck('code')->all();
            $targetSequences = $catalog->pluck('sequence')->all();

            $conflicts = $grades
                ->reject(fn (GradeLevel $grade): bool => in_array($grade->code, $catalogCodes, true))
                ->filter(fn (GradeLevel $grade): bool => in_array($grade->sequence, $targetSequences, true));

            if ($conflicts->isNotEmpty()) {
                throw new RuntimeException('A non-catalog grade level already uses one of the required sequences: '.$conflicts->pluck('code')->join(', '));
            }

            $temporarySequence = max((int) $grades->max('sequence'), (int) max($targetSequences));
            if ($temporarySequence + $catalog->count() > 65535) {
                throw new RuntimeException('Grade-level sequences cannot be safely reordered within the database column limit.');
            }

            foreach ($grades->whereIn('code', $catalogCodes) as $grade) {
                $grade->update(['sequence' => ++$temporarySequence]);
            }

            foreach ($catalog as $attributes) {
                $grade = GradeLevel::query()->firstOrNew(['code' => $attributes['code']]);

                if (! $grade->exists) {
                    $grade->public_id = (string) Str::ulid();
                }

                $grade->fill([
                    'name' => $attributes['name'],
                    'sequence' => $attributes['sequence'],
                    'school_stage' => $attributes['school_stage'],
                    'next_grade_level_id' => null,
                    'is_active' => true,
                ])->save();
            }

            $saved = GradeLevel::query()->whereIn('code', $catalogCodes)->get()->keyBy('code');

            foreach ($catalog as $attributes) {
                $saved[$attributes['code']]->update([
                    'next_grade_level_id' => $attributes['next'] === null ? null : $saved[$attributes['next']]->id,
                ]);
            }
        });
    }
}
