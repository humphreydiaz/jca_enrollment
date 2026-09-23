<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\FeeScheduleItem;
use App\Modules\Billing\Models\FeeType;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use Database\Seeders\Billing\ApprovedSchoolFeesSeeder;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApprovedSchoolFeesTest extends TestCase
{
    use RefreshDatabase;

    /** Totals printed on the scanned approvals in public/images/school_fees_scanned. */
    private const ELEMENTARY_TOTAL = '19903.25';

    private const JUNIOR_HIGH_TOTAL = '21850.00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_it_publishes_the_approved_amounts_and_archives_the_superseded_version(): void
    {
        $this->admin();
        $year = $this->year();
        $grade = $this->grade('Grade 3', 3, 'Elementary', 'G3');
        $old = $this->publishedSchedule($year, $grade, 'Old Grade 3 fees', 16000);

        $this->seed(ApprovedSchoolFeesSeeder::class);

        $this->assertSame('archived', $old->fresh()->status);

        $new = FeeSchedule::query()->where('status', 'published')->where('grade_level_id', $grade->id)->sole();
        $this->assertSame(2, $new->version);
        $this->assertSame(self::ELEMENTARY_TOTAL, $this->approvedCoreTotal($new));
    }

    public function test_junior_high_carries_its_own_approved_total_and_no_computer_fee(): void
    {
        $this->admin();
        $year = $this->year();
        $grade = $this->grade('Grade 8', 8, 'Junior High School', 'G8');
        $this->publishedSchedule($year, $grade, 'Old Grade 8 fees', 27000);

        $this->seed(ApprovedSchoolFeesSeeder::class);

        $new = FeeSchedule::query()->where('status', 'published')->where('grade_level_id', $grade->id)->sole();
        $this->assertSame(self::JUNIOR_HIGH_TOTAL, $this->approvedCoreTotal($new));

        // The breakdown leaves the Junior High computer-fee cell blank.
        $codes = $new->items()->with('feeType')->get()->pluck('feeType.code');
        $this->assertNotContains('COMP', $codes);
        $this->assertContains('ACLAB', $codes);
    }

    public function test_books_and_pe_uniform_are_priced_but_never_required(): void
    {
        $this->admin();
        $year = $this->year();
        $grade = $this->grade('Grade 2', 2, 'Elementary', 'G2');
        $this->publishedSchedule($year, $grade, 'Old Grade 2 fees', 16000);

        $this->seed(ApprovedSchoolFeesSeeder::class);

        $items = FeeSchedule::query()->where('status', 'published')->where('grade_level_id', $grade->id)->sole()
            ->items()->with('feeType')->get()->keyBy(fn (FeeScheduleItem $item) => $item->feeType->code);

        $this->assertSame('6500.00', $items['BKS']->amount);
        $this->assertFalse($items['BKS']->is_required);
        $this->assertSame('800.00', $items['PEU']->amount);
        $this->assertFalse($items['PEU']->is_required);
        $this->assertFalse($items['PEU']->is_default_selected);
    }

    public function test_it_never_rewrites_a_published_version_and_leaves_existing_items_intact(): void
    {
        $this->admin();
        $year = $this->year();
        $grade = $this->grade('Grade 9', 9, 'Junior High School', 'G9');
        $old = $this->publishedSchedule($year, $grade, 'Old Grade 9 fees', 27000);
        $originalItems = $old->items()->get()->map(fn ($i) => [$i->id, (string) $i->amount])->all();

        $this->seed(ApprovedSchoolFeesSeeder::class);

        $this->assertSame(
            $originalItems,
            $old->fresh()->items()->get()->map(fn ($i) => [$i->id, (string) $i->amount])->all(),
        );
    }

    public function test_running_it_twice_does_not_publish_a_duplicate_version(): void
    {
        $this->admin();
        $year = $this->year();
        $grade = $this->grade('Grade 7', 7, 'Junior High School', 'G7');
        $this->publishedSchedule($year, $grade, 'Old Grade 7 fees', 27000);

        $this->seed(ApprovedSchoolFeesSeeder::class);
        $this->seed(ApprovedSchoolFeesSeeder::class);

        $this->assertSame(1, FeeSchedule::query()->where('status', 'published')->where('grade_level_id', $grade->id)->count());
        $this->assertSame(2, FeeSchedule::query()->where('grade_level_id', $grade->id)->count());
    }

    /** The approved collectible fees only — books and PE uniform sit outside the DepEd total. */
    private function approvedCoreTotal(FeeSchedule $schedule): string
    {
        $total = $schedule->items()->with('feeType')->get()
            ->reject(fn (FeeScheduleItem $item) => in_array($item->feeType->code, ['BKS', 'PEU'], true))
            ->sum(fn (FeeScheduleItem $item) => (float) $item->amount);

        return number_format($total, 2, '.', '');
    }

    private function publishedSchedule(AcademicYear $year, GradeLevel $grade, string $name, int $tuition): FeeSchedule
    {
        $schedule = FeeSchedule::query()->create([
            'academic_year_id' => $year->id,
            'grade_level_id' => $grade->id,
            'name' => $name,
            'version' => 1,
            'status' => 'published',
        ]);
        $type = FeeType::query()->firstOrCreate(
            ['code' => 'TUI'],
            ['name' => 'Tuition Fee', 'category' => 'Tuition', 'is_mandatory' => true, 'is_permit_critical' => true],
        );
        FeeScheduleItem::query()->create([
            'fee_schedule_id' => $schedule->id,
            'fee_type_id' => $type->id,
            'description' => 'Tuition Fee',
            'amount' => $tuition.'.00',
            'is_required' => true,
            'is_default_selected' => true,
        ]);

        return $schedule;
    }

    private function year(): AcademicYear
    {
        return AcademicYear::query()->create([
            'code' => 'AF-'.Str::upper(Str::random(5)),
            'name' => 'School Year 2026-2027',
            'starts_on' => '2026-06-01',
            'ends_on' => '2027-03-31',
            'status' => 'open',
        ]);
    }

    private function grade(string $name, int $sequence, string $stage, string $code): GradeLevel
    {
        return GradeLevel::query()->create([
            'code' => $code,
            'name' => $name,
            'sequence' => $sequence,
            'school_stage' => $stage,
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        $user = User::query()->create([
            'name' => 'Fees Admin',
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password' => Hash::make('AdminPass12345'),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);

        return app(AssignRolesAction::class)->execute($user, ['admin'], null);
    }
}
