<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Actions\CreateCurriculumDraftAction;
use App\Modules\Academics\Actions\PublishCurriculumAction;
use App\Modules\Academics\Actions\SaveAcademicCatalogAction;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\FeeType;
use App\Modules\Billing\Models\Payment;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Actions\AuthenticateUserAction;
use App\Modules\People\Models\StaffProfile;
use Database\Seeders\Academics\GradeLevelSeeder;
use Database\Seeders\Capstone\CapstoneDemoSeeder;
use Database\Seeders\Capstone\CapstoneFinance;
use Database\Seeders\Enrollment\NurseryWalkInSeeder;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NurseryWalkInSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_enrolls_a_nursery_walk_in_through_the_whole_process_and_fills_missing_setup(): void
    {
        $this->openSchoolYear();
        $setupAudit = (int) DB::table('audit_logs')->max('id');

        $this->seed(NurseryWalkInSeeder::class);

        $enrollment = Enrollment::query()->with(['student', 'requirements.documentType', 'sectionAssignments'])->sole();
        $this->assertSame('enrolled', $enrollment->status);
        $this->assertSame('NUR', $enrollment->gradeLevel->code);
        $this->assertSame(['draft:submitted', 'submitted:under_review', 'under_review:approved', 'approved:enrolled'],
            DB::table('enrollment_status_history')->orderBy('id')->get()->map(fn ($row) => $row->from_status.':'.$row->to_status)->all());
        $this->assertSame(['BIRTH_CERTIFICATE' => 'verified', 'FORM_138' => 'waived'],
            $enrollment->requirements->mapWithKeys(fn ($requirement) => [$requirement->documentType->code => $requirement->status])->sort()->all());
        $waiver = $enrollment->requirements->firstWhere('documentType.code', 'FORM_138');
        $this->assertTrue(User::query()->findOrFail($waiver->waived_by)->hasRole('admin'));
        $hope = Section::query()->where('name', 'Hope')->whereHas('gradeLevel', fn ($query) => $query->where('code', 'NUR'))->sole();
        $this->assertSame($hope->id, $enrollment->sectionAssignments->sole()->section_id);

        // Missing setup was created: the roster adviser and the posted Pre-elementary fees.
        $adviser = TeachingAssignment::query()->where('section_id', $hope->id)->where('assignment_type', 'adviser')->sole();
        $this->assertSame('18-JCA-946', $adviser->staffProfile->employee_number);
        $this->assertSame('published', FeeSchedule::query()->whereHas('gradeLevel', fn ($query) => $query->where('code', 'NUR'))->sole()->status);
        $assessment = $enrollment->feeAssessments()->sole();
        $this->assertSame('finalized', $assessment->status);
        $expected = (float) CapstoneFinance::STAGE_TOTALS['Pre-elementary'] + (float) CapstoneFinance::BOOKS['NUR'] + (float) CapstoneFinance::PE_UNIFORM['NUR'];
        $this->assertEqualsWithDelta($expected, (float) $assessment->net_total, 0.001);
        $payment = Payment::query()->sole();
        $this->assertSame('posted', $payment->status);
        $this->assertSame('2026-10-23', $payment->received_on->toDateString());

        // The mother signs in with the learner's Student ID; nobody can be texted; nothing is dated later.
        $guardian = app(AuthenticateUserAction::class)->execute($enrollment->student->student_number, CapstoneDemoSeeder::PASSWORD);
        $this->assertNotNull($guardian);
        $this->assertTrue($guardian->hasRole('guardian'));
        $this->assertSame(0, User::query()->whereNotNull('mobile')->count());
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame('2026-10-23 10:07:00', DB::table('audit_logs')->where('id', '>', $setupAudit)->max('occurred_at'));

        // The guardian sees the Nursery daily activities, one per line, instead of an empty class schedule.
        $lines = array_map(fn (string $line) => '<li>'.e($line).'</li>', ['11:00-13:00', 'Free play 11:00', 'Meeting 11:15', 'Wrap-up 12:55']);
        $this->actingAs($guardian)->get(route('guardian.enrollment.progress'))
            ->assertOk()
            ->assertSee('Ma. Lourdes Fernandez')
            ->assertSee('Daily activities:')
            ->assertSeeInOrder($lines, false)
            ->assertDontSee('Schedule not yet configured.');
        $this->actingAs($guardian)->get(route('portal.section.print', $enrollment))
            ->assertOk()
            ->assertSee('Daily activities:')
            ->assertSeeInOrder($lines, false)
            ->assertDontSee('No schedule has been configured.');
        $this->actingAs($adviser->staffProfile->user)->get(route('academics.teacher.assignments'))
            ->assertOk()
            ->assertSee('Manalo, Althea Joy R.')
            ->assertSeeInOrder($lines, false)
            ->assertDontSee('No subjects have been scheduled for this section yet.');
    }

    public function test_it_reuses_existing_setup_and_never_duplicates_the_learner(): void
    {
        $this->openSchoolYear();
        $this->seed(NurseryWalkInSeeder::class);
        $staff = StaffProfile::query()->count();

        try {
            $this->seed(NurseryWalkInSeeder::class);
            $this->fail('A second run must not enroll the same learner again.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('student_first_name', $exception->errors());
        }

        $this->assertSame(1, Enrollment::query()->count());
        $this->assertSame($staff, StaffProfile::query()->count());
        $this->assertSame(1, FeeSchedule::query()->count());
    }

    private function openSchoolYear(): void
    {
        $this->seed([RoleSeeder::class, GradeLevelSeeder::class]);
        $admin = $this->staff('Ana Admin', 'admin');
        $this->staff('Rita Registrar', 'registrar');
        $this->staff('Carla Cashier', 'accounting');
        foreach (CapstoneFinance::FEE_TYPES as $type) {
            FeeType::query()->create([...$type, 'is_active' => true]);
        }

        $year = app(SaveAcademicCatalogAction::class)->execute(new AcademicYear, [
            'code' => '2026-2027', 'name' => 'School Year 2026-2027', 'starts_on' => '2026-06-08', 'ends_on' => '2027-04-08',
            'enrollment_opens_on' => '2026-05-04', 'enrollment_closes_on' => '2026-10-31', 'status' => 'planned',
        ], $admin, 'academics.academic_year.created');
        $curriculum = app(CreateCurriculumDraftAction::class)->execute($year, 'JCA 2026-2027 class program', null, $admin, useOfficialDefault: true);
        app(PublishCurriculumAction::class)->execute($curriculum, $admin);
        app(SaveAcademicCatalogAction::class)->execute($year, ['status' => 'open', 'enrollment_is_open' => true], $admin, 'academics.academic_year.updated');
    }

    private function staff(string $name, string $role): User
    {
        $user = User::query()->create(['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@example.test', 'password' => 'Password12345', 'status' => 'active']);
        app(AssignRolesAction::class)->execute($user, [$role], null);

        return $user;
    }
}
