<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StudentProfile;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use App\Modules\SectionAssignment\Models\SectionAssignmentEvent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds demo enrolled students, guardian accounts, and section assignments.
 *
 * - 5 enrolled students per existing 2026-2027 section (8 sections = 40 students).
 * - A new 2025-2026 school year with 10 enrolled students across promotable grades,
 *   ready to be promoted manually to 2026-2027.
 * - One guardian (parent) account per family; two families share 3 and 2 children.
 *
 * Idempotency: aborts if the 2025-2026 year already exists.
 */
class DemoStudentsSeeder extends Seeder
{
    private const PASSWORD = 'Password123!';

    /** @var array<int,array{name:string,sex:string}> */
    private array $childMale = [];

    private array $childFirst = [
        'male' => ['Mateo', 'Gabriel', 'Lucas', 'Rafael', 'Sebastian', 'Miguel', 'Ethan', 'Nathaniel', 'Andres', 'Joaquin', 'Elias', 'Diego', 'Emilio', 'Vince', 'Josh', 'Enzo', 'Zach', 'Kyle', 'Marco', 'Angelo', 'Adrian', 'Julian', 'Paolo', 'Inigo', 'Luis'],
        'female' => ['Sofia', 'Isabella', 'Andrea', 'Althea', 'Nicole', 'Bea', 'Samantha', 'Chloe', 'Yana', 'Trisha', 'Kaila', 'Mika', 'Zoe', 'Ysabel', 'Danica', 'Julia', 'Patricia', 'Camille', 'Alexa', 'Cassandra', 'Loisa', 'Ella', 'Mae', 'Kyla', 'Reign'],
    ];

    private array $adultFirst = [
        'male' => ['Jose', 'Ramon', 'Antonio', 'Ricardo', 'Eduardo', 'Manuel', 'Roberto', 'Fernando', 'Carlo', 'Marlon', 'Dennis', 'Rolando', 'Arnel', 'Noel', 'Rey', 'Efren', 'Danilo', 'Emmanuel', 'Gerald', 'Christian', 'Benjamin', 'Michael', 'Alvin', 'Rodel', 'Jomar'],
        'female' => ['Maria', 'Rosa', 'Cristina', 'Elena', 'Grace', 'Divina', 'Josefina', 'Aileen', 'Marilou', 'Cherry', 'Liza', 'Analyn', 'Jocelyn', 'Melinda', 'Teresa', 'Corazon', 'Imelda', 'Ana', 'Michelle', 'Rowena', 'Jenny', 'Precious', 'Angelica', 'Rhea', 'Catherine'],
    ];

    private array $surnames = ['Dela Cruz', 'Reyes', 'Santos', 'Bautista', 'Aquino', 'Villanueva', 'Mendoza', 'Gutierrez', 'Ramos', 'Del Rosario', 'Castillo', 'Navarro', 'Domingo', 'Salvador', 'Tolentino', 'Mercado', 'Panganiban', 'Rosales', 'Fernandez', 'Ocampo', 'Manalo', 'Cabrera', 'Espinosa', 'Lim', 'Tan', 'Sy', 'Valdez', 'Enriquez', 'Alonzo', 'Marasigan', 'Dizon', 'Rivera', 'Soriano', 'Galang', 'Sarmiento', 'Gonzales', 'Ignacio', 'Lacson', 'Padilla', 'Hernandez', 'Bringas', 'Pascual', 'Torres', 'Flores', 'Bermudez', 'Andrada', 'Quinto'];

    /** @var array<string,bool> */
    private array $usedEmails = [];

    private int $studentSeq = 0;

    public function run(): void
    {
        if (AcademicYear::query()->where('code', '2025-2026')->exists()) {
            $this->command->warn('DemoStudentsSeeder: 2025-2026 already exists — aborting to avoid double seeding.');

            return;
        }

        $admin = User::query()->whereHas('roles', fn ($q) => $q->where('code', 'admin'))->first() ?? User::query()->firstOrFail();
        $year2627 = AcademicYear::query()->where('code', '2026-2027')->firstOrFail();

        DB::transaction(function () use ($admin, $year2627): void {
            $year2526 = AcademicYear::query()->create([
                'code' => '2025-2026',
                'name' => 'School Year 2025-2026',
                'starts_on' => '2025-06-09',
                'ends_on' => '2026-03-27',
                'enrollment_opens_on' => '2025-04-01',
                'enrollment_closes_on' => '2025-06-06',
                'status' => 'closed',
            ]);

            $grades = GradeLevel::query()->orderBy('sequence')->get()->keyBy('id');
            $sections2627 = Section::query()->where('academic_year_id', $year2627->id)->orderBy('grade_level_id')->get()->keyBy('grade_level_id');
            $nonTerminal = $grades->filter(fn (GradeLevel $g) => $g->next_grade_level_id !== null)->values();

            // 2025-2026 sections for each promotable grade.
            $sections2526 = [];
            foreach ($nonTerminal as $grade) {
                $sections2526[$grade->id] = Section::query()->create([
                    'academic_year_id' => $year2526->id,
                    'grade_level_id' => $grade->id,
                    'name' => $sections2627->get($grade->id)?->name ?? 'Section A',
                    'capacity' => 15,
                    'sort_order' => 1,
                    'status' => 'active',
                ]);
            }

            // Build 50 slots: 40 for 2026-2027 (5 per section), 10 for 2025-2026 (2 per promotable grade).
            $slots = [];
            foreach ($sections2627 as $gradeId => $section) {
                for ($i = 0; $i < 5; $i++) {
                    $slots[] = ['year' => $year2627, 'grade' => $grades->get($gradeId), 'section' => $section, 'enrolledOn' => '2026-05-15'];
                }
            }
            foreach ($nonTerminal as $grade) {
                for ($i = 0; $i < 2; $i++) {
                    $slots[] = ['year' => $year2526, 'grade' => $grade, 'section' => $sections2526[$grade->id], 'enrolledOn' => '2025-06-20'];
                }
            }

            // Families: two sibling groups (by slot index) + one family per remaining slot.
            $siblingGroups = [[0, 20, 39], [5, 40]]; // Grade 1/8/12 (3 kids); Grade 2 + a 2025-2026 kid (2 kids)
            $familyOfSlot = array_fill(0, count($slots), null);
            $families = [];
            foreach ($siblingGroups as $group) {
                $famId = count($families);
                $families[$famId] = [];
                foreach ($group as $slotIdx) {
                    $familyOfSlot[$slotIdx] = $famId;
                    $families[$famId][] = $slotIdx;
                }
            }
            foreach ($slots as $idx => $slot) {
                if ($familyOfSlot[$idx] === null) {
                    $famId = count($families);
                    $families[$famId] = [$idx];
                    $familyOfSlot[$idx] = $famId;
                }
            }

            // Create each family: one guardian account, then its children.
            $childCursor = ['male' => 0, 'female' => 0];
            $adultCursor = ['male' => 0, 'female' => 0];

            foreach ($families as $famId => $slotIdxs) {
                $surname = $this->surnames[$famId % count($this->surnames)];
                $guardianSex = $famId % 2 === 0 ? 'female' : 'male';
                $guardianFirst = $this->adultFirst[$guardianSex][$adultCursor[$guardianSex]++ % count($this->adultFirst[$guardianSex])];
                $relationship = $guardianSex === 'female' ? 'Mother' : 'Father';

                $email = $this->uniqueEmail($guardianFirst, $surname);
                $user = User::query()->create([
                    'name' => $guardianFirst.' '.$surname,
                    'email' => $email,
                    'password' => self::PASSWORD,
                    'status' => 'active',
                    'must_change_password' => false,
                    'password_changed_at' => now(),
                    'email_verified_at' => now(),
                ]);
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => 5, // guardian
                    'active_assignment_key' => $user->id.':5',
                    'assigned_by' => $admin->id,
                    'assigned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $guardian = GuardianProfile::query()->create([
                    'user_id' => $user->id,
                    'first_name' => $guardianFirst,
                    'last_name' => $surname,
                    'mobile' => '+639'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
                    'email' => $email,
                    'preferred_contact_method' => 'email',
                    'status' => 'active',
                ]);

                foreach ($slotIdxs as $slotIdx) {
                    $slot = $slots[$slotIdx];
                    $childSex = $slotIdx % 2 === 0 ? 'male' : 'female';
                    $childFirst = $this->childFirst[$childSex][$childCursor[$childSex]++ % count($this->childFirst[$childSex])];
                    $middle = $this->surnames[($famId + 7) % count($this->surnames)];

                    $this->studentSeq++;
                    $student = StudentProfile::query()->create([
                        'student_number' => 'S-'.$slot['year']->code.'-'.str_pad((string) $this->studentSeq, 4, '0', STR_PAD_LEFT),
                        'first_name' => $childFirst,
                        'middle_name' => $middle,
                        'last_name' => $surname,
                        'sex' => $childSex,
                        'birth_date' => Carbon::parse($slot['enrolledOn'])->subYears(5 + (int) $slot['grade']->sequence)->toDateString(),
                        'birth_place' => 'Quezon City',
                        'mother_tongue' => 'Tagalog',
                        'current_address' => 'Barangay Commonwealth, Quezon City',
                        'permanent_address' => 'Barangay Commonwealth, Quezon City',
                        'status' => 'active',
                    ]);

                    GuardianStudent::query()->create([
                        'guardian_id' => $guardian->id,
                        'student_id' => $student->id,
                        'relationship_type' => $relationship,
                        'is_primary_contact' => true,
                        'may_receive_sms' => true,
                        'may_view_financials' => true,
                        'may_manage_enrollment' => true,
                        'has_legal_authority' => true,
                        'verified_by' => $admin->id,
                        'verified_at' => now(),
                        'effective_from' => $slot['enrolledOn'],
                    ]);

                    $enrolledAt = Carbon::parse($slot['enrolledOn']);
                    $enrollment = Enrollment::query()->create([
                        'application_number' => 'JCA-'.$enrolledAt->format('Y').'-'.Str::upper(Str::random(8)),
                        'student_id' => $student->id,
                        'academic_year_id' => $slot['year']->id,
                        'grade_level_id' => $slot['grade']->id,
                        'applicant_guardian_id' => $guardian->id,
                        'student_category' => 'regular',
                        'guardian_relationship' => $relationship,
                        'status' => 'enrolled',
                        'active_enrollment_key' => $student->id.':'.$slot['year']->id,
                        'duplicate_review_status' => 'clear',
                        'contact_verified_at' => $enrolledAt->copy()->subDays(20),
                        'submitted_at' => $enrolledAt->copy()->subDays(21),
                        'approved_at' => $enrolledAt->copy()->subDays(5),
                        'enrolled_at' => $enrolledAt,
                        'decided_by' => $admin->id,
                        'decision_reason' => 'Section assignment confirmed.',
                        'lock_version' => 2,
                    ]);

                    $assignment = SectionAssignment::query()->create([
                        'enrollment_id' => $enrollment->id,
                        'section_id' => $slot['section']->id,
                        'status' => 'confirmed',
                        'active_assignment_key' => (string) $enrollment->id,
                        'confirmed_by' => $admin->id,
                        'confirmed_at' => $enrolledAt,
                        'effective_from' => $enrolledAt->toDateString(),
                        'support_suitability_confirmed' => false,
                        'is_capacity_override' => false,
                    ]);
                    SectionAssignmentEvent::query()->create([
                        'section_assignment_id' => $assignment->id,
                        'enrollment_id' => $enrollment->id,
                        'event_type' => 'confirmed',
                        'to_section_id' => $slot['section']->id,
                        'actor_user_id' => $admin->id,
                        'is_capacity_override' => false,
                        'occurred_at' => $enrolledAt,
                    ]);
                }
            }
        });

        $this->command->info('DemoStudentsSeeder complete. Shared password for all guardian accounts: '.self::PASSWORD);
    }

    private function uniqueEmail(string $first, string $surname): string
    {
        $base = Str::of($first.'.'.$surname)->lower()->replace(' ', '')->ascii()->toString();
        $email = $base.'@jca.test';
        $n = 1;
        while (isset($this->usedEmails[$email])) {
            $email = $base.(++$n).'@jca.test';
        }
        $this->usedEmails[$email] = true;

        return $email;
    }
}
