<?php

namespace Tests\Feature;

use App\Modules\Academics\Models\GradeLevel;
use Database\Seeders\Academics\GradeLevelSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GradeLevelSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_assigns_public_ids_while_model_events_are_suppressed(): void
    {
        $this->seed(DatabaseSeeder::class);

        $publicIds = GradeLevel::query()->pluck('public_id');

        $this->assertCount(13, $publicIds);
        $this->assertSame(13, $publicIds->unique()->count());
        $this->assertTrue($publicIds->every(fn (string $publicId): bool => Str::isUlid($publicId)));
    }

    public function test_it_populates_and_safely_reorders_the_complete_grade_progression(): void
    {
        $gradeOne = GradeLevel::query()->create([
            'code' => 'G1',
            'name' => 'Old Grade One Name',
            'sequence' => 1,
            'school_stage' => null,
            'is_active' => false,
        ]);
        GradeLevel::query()->create([
            'code' => 'G7',
            'name' => 'Grade 7',
            'sequence' => 7,
            'school_stage' => 'Junior High School',
            'is_active' => true,
        ]);

        $this->seed(GradeLevelSeeder::class);
        $this->seed(GradeLevelSeeder::class);

        $this->assertDatabaseCount('grade_levels', 13);
        $this->assertSame($gradeOne->id, GradeLevel::query()->where('code', 'G1')->value('id'));
        $this->assertSame(
            [
                'NUR' => 1, 'JK' => 2, 'SK' => 3,
                'G1' => 4, 'G2' => 5, 'G3' => 6, 'G4' => 7, 'G5' => 8, 'G6' => 9,
                'G7' => 10, 'G8' => 11, 'G9' => 12, 'G10' => 13,
            ],
            GradeLevel::query()->orderBy('sequence')->pluck('sequence', 'code')->all(),
        );
        foreach (['NUR' => 'Nursery', 'JK' => 'Junior Kinder', 'SK' => 'Senior Kinder'] as $code => $name) {
            $this->assertDatabaseHas('grade_levels', [
                'code' => $code,
                'name' => $name,
                'school_stage' => 'Pre-elementary',
                'is_active' => true,
            ]);
        }

        $progression = GradeLevel::query()->with('nextGradeLevel')->orderBy('sequence')->get();
        $this->assertSame(
            ['JK', 'SK', 'G1', 'G2', 'G3', 'G4', 'G5', 'G6', 'G7', 'G8', 'G9', 'G10', null],
            $progression->map(fn (GradeLevel $grade): ?string => $grade->nextGradeLevel?->code)->all(),
        );
    }
}
