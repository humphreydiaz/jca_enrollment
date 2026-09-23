<?php

namespace Database\Seeders\Hostinger;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\FeeScheduleItem;
use App\Modules\Billing\Models\FeeType;
use App\Modules\Billing\Models\PaymentPlanOption;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\UserRole;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use App\Modules\SectionAssignment\Models\SectionAssignmentEvent;
use App\Modules\Shared\Audit\AuditRecorder;
use Database\Seeders\Academics\GradeLevelSeeder;
use Database\Seeders\Identity\RoleSeeder;
use Database\Seeders\Requirements\RequirementsBaselineSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Adds a deterministic, synthetic Hostinger rehearsal dataset without deleting
 * or rewriting existing school records.
 *
 * The learner, guardian, and teacher identities are fictional. Reserved
 * example.com addresses and null mobile numbers prevent accidental contact.
 * Financial schedules are intentionally left in draft and payment choices are
 * inactive until the school completes its separate finance approval.
 */
class HostingerSampleDataSeeder extends Seeder
{
    private const YEAR = [
        'code' => '2026-2027',
        'name' => 'School Year 2026-2027',
        'starts_on' => '2026-06-08',
        'ends_on' => '2027-04-08',
        'enrollment_opens_on' => '2026-05-08',
        'enrollment_closes_on' => '2026-08-20',
        'status' => 'open',
    ];

    private const SUBJECTS = [
        ['code' => 'ENG', 'name' => 'English'],
        ['code' => 'FIL', 'name' => 'Filipino'],
        ['code' => 'MATH', 'name' => 'Mathematics'],
        ['code' => 'SCI', 'name' => 'Science'],
    ];

    private const SECTIONS = [
        ['grade' => 'G1', 'name' => 'Faith', 'capacity' => 15],
        ['grade' => 'G2', 'name' => 'Hope', 'capacity' => 15],
        ['grade' => 'G3', 'name' => 'Love', 'capacity' => 15],
        ['grade' => 'G7', 'name' => 'Peace', 'capacity' => 15],
        ['grade' => 'G8', 'name' => 'Joy', 'capacity' => 15],
        ['grade' => 'G9', 'name' => 'Kindness', 'capacity' => 15],
    ];

    private const TEACHERS = [
        ['name' => 'Jose Ramos', 'email' => 'jose.ramos@example.com', 'employee_number' => 'HST-TCH-001'],
        ['name' => 'Maria Teresa Bautista', 'email' => 'maria.bautista@example.com', 'employee_number' => 'HST-TCH-002'],
        ['name' => 'Carlo Mendoza', 'email' => 'carlo.mendoza@example.com', 'employee_number' => 'HST-TCH-003'],
        ['name' => 'Liza Navarro', 'email' => 'liza.navarro@example.com', 'employee_number' => 'HST-TCH-004'],
        ['name' => 'Ramon Castillo', 'email' => 'ramon.castillo@example.com', 'employee_number' => 'HST-TCH-005'],
        ['name' => 'Grace Villanueva', 'email' => 'grace.villanueva@example.com', 'employee_number' => 'HST-TCH-006'],
    ];

    private const STUDENT_FIRST_NAMES = [
        'Mateo', 'Sofia', 'Gabriel', 'Isabella', 'Lucas', 'Andrea', 'Rafael', 'Althea', 'Sebastian', 'Nicole',
        'Miguel', 'Samantha', 'Ethan', 'Chloe', 'Nathaniel', 'Kaila', 'Andres', 'Mika', 'Joaquin', 'Danica',
        'Diego', 'Julia', 'Emilio', 'Patricia', 'Vince', 'Camille', 'Enzo', 'Cassandra', 'Marco', 'Ella',
        'Angelo', 'Kyla', 'Adrian', 'Ysabel', 'Julian', 'Bea', 'Paolo', 'Trisha', 'Luis', 'Alexa',
        'Inigo', 'Mae', 'Kyle', 'Zoe', 'Elias', 'Reign', 'Josh', 'Yana', 'Zach', 'Loisa',
    ];

    private const SURNAMES = [
        'Dela Cruz', 'Reyes', 'Santos', 'Bautista', 'Aquino', 'Villanueva', 'Mendoza', 'Gutierrez', 'Ramos', 'Del Rosario',
        'Castillo', 'Navarro', 'Domingo', 'Salvador', 'Tolentino', 'Mercado', 'Panganiban', 'Rosales', 'Fernandez', 'Ocampo',
        'Manalo', 'Cabrera', 'Espinosa', 'Valdez', 'Enriquez', 'Alonzo', 'Marasigan', 'Dizon', 'Rivera', 'Soriano',
        'Galang', 'Sarmiento', 'Gonzales', 'Ignacio', 'Lacson', 'Padilla', 'Hernandez', 'Bringas', 'Pascual', 'Torres',
        'Flores', 'Bermudez', 'Andrada', 'Quinto', 'Aguilar', 'De Guzman', 'Evangelista', 'Macaraeg', 'Natividad', 'Sison',
    ];

    private const GUARDIAN_FIRST_NAMES = [
        'Maria', 'Jose', 'Rosa', 'Ramon', 'Cristina', 'Antonio', 'Elena', 'Ricardo', 'Grace', 'Eduardo',
        'Divina', 'Manuel', 'Josefina', 'Roberto', 'Aileen', 'Fernando', 'Marilou', 'Carlo', 'Cherry', 'Marlon',
        'Liza', 'Dennis', 'Analyn', 'Rolando', 'Jocelyn', 'Arnel', 'Melinda', 'Noel', 'Teresa', 'Rey',
        'Corazon', 'Efren', 'Imelda', 'Danilo', 'Ana', 'Emmanuel', 'Michelle', 'Gerald', 'Rowena', 'Christian',
        'Catherine', 'Benjamin', 'Rhea', 'Michael', 'Precious', 'Alvin', 'Janet', 'Rodel',
    ];

    private const FEE_TYPES = [
        ['code' => 'TUI', 'name' => 'Tuition Fee', 'category' => 'Tuition', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => true, 'max_quantity' => 1],
        ['code' => 'MSC', 'name' => 'Miscellaneous Fee', 'category' => 'School Fee', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => true, 'max_quantity' => 1],
        ['code' => 'COMP', 'name' => 'Computer Fee', 'category' => 'School Fee', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => false, 'max_quantity' => 1],
        ['code' => 'ACLAB', 'name' => 'AC/Lab Fee', 'category' => 'School Fee', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => false, 'max_quantity' => 1],
        ['code' => 'SID', 'name' => 'School ID', 'category' => 'School Fee', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => false, 'max_quantity' => 1],
        ['code' => 'INS', 'name' => 'Insurance (Group)', 'category' => 'School Fee', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => false, 'max_quantity' => 1],
        ['code' => 'REG', 'name' => 'Registration', 'category' => 'School Fee', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => false, 'max_quantity' => 1],
        ['code' => 'BKS', 'name' => 'Books', 'category' => 'Learning Materials', 'is_mandatory' => false, 'is_conditional' => true, 'is_permit_critical' => false, 'max_quantity' => 1],
        ['code' => 'PEU', 'name' => 'PE Uniform', 'category' => 'Uniform', 'is_mandatory' => false, 'is_conditional' => true, 'is_permit_critical' => false, 'max_quantity' => 1],
    ];

    private const CORE_FEES = [
        'Elementary' => [
            ['code' => 'TUI', 'description' => 'Tuition Fee', 'amount' => '11815.25'],
            ['code' => 'MSC', 'description' => 'Miscellaneous Fee', 'amount' => '4668.00'],
            ['code' => 'COMP', 'description' => 'Computer Fee', 'amount' => '1650.00'],
            ['code' => 'ACLAB', 'description' => 'AC/Lab Fee', 'amount' => '1000.00'],
            ['code' => 'SID', 'description' => 'School ID', 'amount' => '110.00'],
            ['code' => 'INS', 'description' => 'Insurance (Group)', 'amount' => '110.00'],
            ['code' => 'REG', 'description' => 'Registration', 'amount' => '550.00'],
        ],
        'Junior High School' => [
            ['code' => 'TUI', 'description' => 'Tuition Fee', 'amount' => '11250.00'],
            ['code' => 'MSC', 'description' => 'Miscellaneous Fee', 'amount' => '5550.00'],
            ['code' => 'ACLAB', 'description' => 'AC/Lab Fee', 'amount' => '3650.00'],
            ['code' => 'SID', 'description' => 'School ID', 'amount' => '200.00'],
            ['code' => 'INS', 'description' => 'Insurance (Group)', 'amount' => '200.00'],
            ['code' => 'REG', 'description' => 'Registration', 'amount' => '1000.00'],
        ],
    ];

    private const BOOK_PRICES = [
        'G1' => '6300.00', 'G2' => '6500.00', 'G3' => '6500.00',
        'G7' => '7500.00', 'G8' => '7500.00', 'G9' => '7500.00',
    ];

    private const PE_UNIFORM_PRICES = [
        'G1' => '800.00', 'G2' => '800.00', 'G3' => '800.00',
        'G7' => '900.00', 'G8' => '900.00', 'G9' => '900.00',
    ];

    /** @var array<string, int> */
    private const BIRTH_YEARS = [
        'G1' => 2019, 'G2' => 2018, 'G3' => 2017,
        'G7' => 2013, 'G8' => 2012, 'G9' => 2011,
    ];

    public function run(?string $temporaryPassword = null): void
    {
        $this->call([RoleSeeder::class, GradeLevelSeeder::class]);

        $actor = User::query()
            ->whereHas('roles', fn ($query) => $query->where('code', 'admin'))
            ->orderBy('id')
            ->first();

        if ($actor === null) {
            throw new RuntimeException('An existing Admin account is required before loading the Hostinger sample dataset.');
        }

        $temporaryPassword = $this->resolveTemporaryPassword($temporaryPassword);

        DB::transaction(function () use ($actor, $temporaryPassword): void {
            $year = AcademicYear::query()->firstOrCreate(
                ['code' => self::YEAR['code']],
                collect(self::YEAR)->except('code')->all(),
            );

            app(RequirementsBaselineSeeder::class)->run();

            $subjects = collect(self::SUBJECTS)->mapWithKeys(function (array $attributes): array {
                $subject = Subject::query()->firstOrCreate(
                    ['code' => $attributes['code']],
                    ['name' => $attributes['name'], 'description' => null, 'is_active' => true],
                );

                return [$subject->code => $subject];
            });

            $sections = $this->createSections($year, $subjects);
            $this->createRequestedStaff($actor, $temporaryPassword);
            $this->createTeachersAndAssignments($actor, $year, $sections);
            $this->createLearners($actor, $year, $sections);
            $this->createDraftFinancialConfiguration($year, $sections);

            app(AuditRecorder::class)->record($actor, 'hosted.sample_dataset.seeded', $year, [
                'learners' => 50,
                'guardians' => 48,
                'teachers' => 6,
                'sections' => count(self::SECTIONS),
                'financial_status' => 'draft',
            ]);
        });

        $this->command?->info('Hostinger sample dataset loaded without deleting existing records.');
        $this->command?->warn('The two requested staff accounts must change their temporary password at first sign-in.');
        $this->command?->warn('Fee schedules remain draft and payment-plan choices remain inactive pending school approval.');
    }

    private function resolveTemporaryPassword(?string $temporaryPassword): string
    {
        if ($temporaryPassword === null) {
            $temporaryPassword = $this->command?->secret('Temporary password for the Registrar and Accounting accounts');
        }

        if (! is_string($temporaryPassword) || mb_strlen($temporaryPassword) < 12) {
            throw new RuntimeException('Enter a temporary staff password containing at least 12 characters.');
        }

        return $temporaryPassword;
    }

    /**
     * @param  Collection<string, Subject>  $subjects
     * @return Collection<string, Section>
     */
    private function createSections(AcademicYear $year, $subjects)
    {
        $grades = GradeLevel::query()->whereIn('code', collect(self::SECTIONS)->pluck('grade'))->get()->keyBy('code');
        $sections = collect();

        foreach (self::SECTIONS as $index => $attributes) {
            $grade = $grades->get($attributes['grade'])
                ?? throw new RuntimeException("Missing grade level {$attributes['grade']}.");

            $section = Section::query()->firstOrCreate(
                [
                    'academic_year_id' => $year->id,
                    'grade_level_id' => $grade->id,
                    'name' => $attributes['name'],
                ],
                ['capacity' => $attributes['capacity'], 'sort_order' => $index + 1, 'status' => 'active'],
            );

            foreach ($subjects as $subject) {
                SectionSubject::query()->firstOrCreate(
                    ['section_id' => $section->id, 'subject_id' => $subject->id],
                    ['schedule_text' => null],
                );
            }

            $sections->put($attributes['grade'], $section);
        }

        return $sections;
    }

    private function createRequestedStaff(User $actor, string $temporaryPassword): void
    {
        $this->createActiveStaff(
            $actor,
            'Jenny Santos',
            'jenny@gmail.com',
            'registrar',
            'HST-REG-001',
            'Registrar',
            $temporaryPassword,
        );
        $this->createActiveStaff(
            $actor,
            'Angelica Garcia',
            'angelica@gmail.com',
            'accounting',
            'HST-ACC-001',
            'Accounting Staff',
            $temporaryPassword,
        );
    }

    private function createActiveStaff(
        User $actor,
        string $name,
        string $email,
        string $roleCode,
        string $employeeNumber,
        string $positionTitle,
        string $temporaryPassword,
    ): User {
        $user = User::query()->where('email', $email)->first();

        if ($user !== null && $user->name !== $name) {
            throw new RuntimeException("The email {$email} already belongs to a different account; no changes were made.");
        }

        if ($user === null) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($temporaryPassword),
                'status' => AccountStatus::Active,
                'must_change_password' => true,
                'password_changed_at' => null,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $this->assignRole($user, $roleCode, $actor);
        StaffProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'employee_number' => $employeeNumber,
                'position_title' => $positionTitle,
                'active_from' => self::YEAR['starts_on'],
            ],
        );

        return $user;
    }

    /**
     * @param  Collection<string, Section>  $sections
     */
    private function createTeachersAndAssignments(User $actor, AcademicYear $year, $sections): void
    {
        foreach (self::TEACHERS as $index => $attributes) {
            $user = User::query()->where('email', $attributes['email'])->first();

            if ($user !== null && $user->name !== $attributes['name']) {
                throw new RuntimeException("The reserved sample email {$attributes['email']} already belongs to another account.");
            }

            $user ??= User::query()->create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => null,
                'status' => AccountStatus::Invited,
                'must_change_password' => true,
            ]);

            $this->assignRole($user, 'teacher', $actor);
            $profile = StaffProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'employee_number' => $attributes['employee_number'],
                    'position_title' => 'Teacher',
                    'active_from' => $year->starts_on,
                ],
            );

            $section = $sections->values()->get($index);
            TeachingAssignment::query()->firstOrCreate(
                [
                    'staff_profile_id' => $profile->id,
                    'section_id' => $section->id,
                    'section_subject_id' => null,
                    'assignment_type' => 'adviser',
                ],
                [
                    'active_from' => $year->starts_on,
                    'active_until' => $year->ends_on,
                    'is_active' => true,
                ],
            );
        }
    }

    private function assignRole(User $user, string $roleCode, User $actor): void
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        if (UserRole::query()->where('user_id', $user->id)->where('role_id', $role->id)->whereNull('revoked_at')->exists()) {
            return;
        }

        UserRole::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'active_assignment_key' => $user->id.':'.$role->id,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
        ]);
    }

    /**
     * @param  Collection<string, Section>  $sections
     */
    private function createLearners(User $actor, AcademicYear $year, $sections): void
    {
        $grades = GradeLevel::query()->whereIn('code', $sections->keys())->get()->keyBy('id');
        $sectionList = $sections->values();
        $guardianCache = [];

        for ($index = 0; $index < 50; $index++) {
            $number = $index + 1;
            $section = $sectionList->get($index % $sectionList->count());
            $grade = $grades->get($section->grade_level_id);
            $studentNumber = 'SIM-2026-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $student = StudentProfile::query()->where('student_number', $studentNumber)->first();

            if ($student === null) {
                $birthYear = self::BIRTH_YEARS[$grade->code];
                $student = StudentProfile::query()->create([
                    'student_number' => $studentNumber,
                    'first_name' => self::STUDENT_FIRST_NAMES[$index],
                    'middle_name' => self::SURNAMES[($index + 9) % count(self::SURNAMES)],
                    'last_name' => self::SURNAMES[$index],
                    'sex' => $index % 2 === 0 ? 'male' : 'female',
                    'birth_date' => Carbon::create($birthYear, ($index % 12) + 1, ($index % 27) + 1)->toDateString(),
                    'birth_place' => 'Philippines',
                    'mother_tongue' => 'Filipino',
                    'status' => 'active',
                ]);
            }

            // Two synthetic families each have two siblings, producing 48 guardians for 50 learners.
            $guardianIndex = match ($index) {
                20 => 0,
                21 => 1,
                default => $index < 20 ? $index : $index - 2,
            };
            $guardian = $guardianCache[$guardianIndex] ??= $this->guardian($guardianIndex);

            GuardianStudent::query()->firstOrCreate(
                ['guardian_id' => $guardian->id, 'student_id' => $student->id],
                [
                    'relationship_type' => $guardianIndex % 2 === 0 ? 'Mother' : 'Father',
                    'is_primary_contact' => true,
                    'may_receive_sms' => false,
                    'may_view_financials' => true,
                    'may_manage_enrollment' => true,
                    'has_legal_authority' => true,
                    'verified_by' => $actor->id,
                    'verified_at' => now(),
                    'effective_from' => self::YEAR['starts_on'],
                ],
            );

            $applicationNumber = 'SIM-2026-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $enrollment = Enrollment::query()->firstOrCreate(
                ['application_number' => $applicationNumber],
                [
                    'student_id' => $student->id,
                    'academic_year_id' => $year->id,
                    'grade_level_id' => $grade->id,
                    'applicant_guardian_id' => $guardian->id,
                    'student_category' => 'regular',
                    'guardian_relationship' => $guardianIndex % 2 === 0 ? 'Mother' : 'Father',
                    'status' => 'enrolled',
                    'active_enrollment_key' => $student->id.':'.$year->id,
                    'duplicate_review_status' => 'clear',
                    'contact_verified_at' => Carbon::parse(self::YEAR['starts_on'])->subDays(20),
                    'submitted_at' => Carbon::parse(self::YEAR['starts_on'])->subDays(21),
                    'approved_at' => Carbon::parse(self::YEAR['starts_on'])->subDays(5),
                    'enrolled_at' => self::YEAR['starts_on'],
                    'decided_by' => $actor->id,
                    'decision_reason' => 'Enrollment record prepared for the hosted sample dataset.',
                    'lock_version' => 2,
                ],
            );

            $assignment = SectionAssignment::query()->firstOrCreate(
                ['enrollment_id' => $enrollment->id, 'status' => 'confirmed'],
                [
                    'section_id' => $section->id,
                    'active_assignment_key' => (string) $enrollment->id,
                    'confirmed_by' => $actor->id,
                    'confirmed_at' => self::YEAR['starts_on'],
                    'effective_from' => self::YEAR['starts_on'],
                    'support_suitability_confirmed' => false,
                    'is_capacity_override' => false,
                ],
            );

            SectionAssignmentEvent::query()->firstOrCreate(
                ['section_assignment_id' => $assignment->id, 'event_type' => 'confirmed'],
                [
                    'enrollment_id' => $enrollment->id,
                    'to_section_id' => $section->id,
                    'actor_user_id' => $actor->id,
                    'is_capacity_override' => false,
                    'occurred_at' => self::YEAR['starts_on'],
                ],
            );
        }
    }

    private function guardian(int $index): GuardianProfile
    {
        $number = $index + 1;
        $email = 'family'.str_pad((string) $number, 2, '0', STR_PAD_LEFT).'@example.com';

        return GuardianProfile::query()->firstOrCreate(
            ['email' => $email],
            [
                'first_name' => self::GUARDIAN_FIRST_NAMES[$index],
                'middle_name' => self::SURNAMES[($index + 13) % count(self::SURNAMES)],
                'last_name' => self::SURNAMES[$index],
                'mobile' => null,
                'preferred_contact_method' => 'email',
                'status' => 'active',
            ],
        );
    }

    /**
     * @param  Collection<string, Section>  $sections
     */
    private function createDraftFinancialConfiguration(AcademicYear $year, $sections): void
    {
        $feeTypes = collect(self::FEE_TYPES)->mapWithKeys(function (array $attributes): array {
            $type = FeeType::query()->firstOrCreate(
                ['code' => $attributes['code']],
                [...collect($attributes)->except('code')->all(), 'is_active' => true],
            );

            return [$type->code => $type];
        });

        $grades = GradeLevel::query()->whereIn('id', $sections->pluck('grade_level_id'))->get()->keyBy('id');

        foreach ($sections as $section) {
            $grade = $grades->get($section->grade_level_id);
            $schedule = FeeSchedule::query()
                ->where('academic_year_id', $year->id)
                ->where('grade_level_id', $grade->id)
                ->where('version', 1)
                ->first();

            if ($schedule === null) {
                $schedule = FeeSchedule::query()->create([
                    'academic_year_id' => $year->id,
                    'grade_level_id' => $grade->id,
                    'name' => $grade->name.' — School Year 2026-2027 Fees',
                    'version' => 1,
                    'status' => 'draft',
                    'effective_on' => $year->starts_on,
                ]);

                foreach ($this->feeLines($grade) as $line) {
                    FeeScheduleItem::query()->create([
                        'fee_schedule_id' => $schedule->id,
                        'fee_type_id' => $feeTypes[$line['code']]->id,
                        'description' => $line['description'],
                        'amount' => $line['amount'],
                        'is_required' => $line['is_required'],
                        'is_default_selected' => $line['is_default_selected'],
                    ]);
                }
            }

            foreach ([
                ['code' => 'full', 'name' => 'Full Payment'],
                ['code' => 'monthly', 'name' => 'Monthly'],
                ['code' => 'quarterly', 'name' => 'Quarterly'],
                ['code' => 'semiannual', 'name' => 'Semiannual'],
            ] as $option) {
                PaymentPlanOption::query()->firstOrCreate(
                    ['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'code' => $option['code']],
                    ['name' => $option['name'], 'is_active' => false],
                );
            }
        }
    }

    /**
     * @return list<array{code: string, description: string, amount: string, is_required: bool, is_default_selected: bool}>
     */
    private function feeLines(GradeLevel $grade): array
    {
        $lines = array_map(
            fn (array $line): array => [...$line, 'is_required' => true, 'is_default_selected' => true],
            self::CORE_FEES[$grade->school_stage],
        );
        $lines[] = [
            'code' => 'BKS',
            'description' => 'Books (estimated price)',
            'amount' => self::BOOK_PRICES[$grade->code],
            'is_required' => false,
            'is_default_selected' => true,
        ];
        $lines[] = [
            'code' => 'PEU',
            'description' => 'PE Uniform',
            'amount' => self::PE_UNIFORM_PRICES[$grade->code],
            'is_required' => false,
            'is_default_selected' => false,
        ];

        return $lines;
    }
}
