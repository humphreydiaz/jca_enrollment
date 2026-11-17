<?php

namespace Database\Seeders\Billing;

use App\Models\User;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Billing\Actions\ManageFeeScheduleAction;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\FeeScheduleItem;
use App\Modules\Billing\Models\FeeType;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The school's official fee structure, transcribed from the scanned approvals
 * in public/images/school_fees_scanned:
 *
 *   - "Approved School Fees.jpg" — DepEd Region 1 letter of 11 June 2025
 *     acknowledging the increase effective SY 2025-2026 (Kindergarten total
 *     PHP 18,088.25; Grades I-VI total PHP 19,903.25).
 *   - "School Fees Complete Breakdown.jpg" — the school's own itemised sheet
 *     for the same totals, plus Grades 7-10 (Junior High) at PHP 21,850.00,
 *     the per-grade book prices, and the registration/monthly notes.
 *   - "PE Uniform.jpg" — PE uniform price list.
 *
 * A published fee schedule is immutable, so this seeder never edits one. It
 * publishes a NEW version per grade carrying the official amounts and archives
 * the version it supersedes. Assessments already issued keep their own snapshot
 * and are untouched; re-billing a student is an accounting decision.
 */
class ApprovedSchoolFeesSeeder extends Seeder
{
    /**
     * Every fee type the official breakdown references. An existing type is
     * left exactly as configured; only missing ones are created, so this can
     * run against a school database without disturbing its catalog.
     */
    private const FEE_TYPES = [
        ['code' => 'TUI', 'name' => 'Tuition Fee', 'category' => 'Tuition', 'is_mandatory' => true, 'is_permit_critical' => true],
        ['code' => 'MSC', 'name' => 'Miscellaneous Fee', 'category' => 'School Fee', 'is_mandatory' => true, 'is_permit_critical' => true],
        ['code' => 'COMP', 'name' => 'Computer Fee', 'category' => 'School Fee', 'is_mandatory' => true, 'is_permit_critical' => false],
        ['code' => 'ACLAB', 'name' => 'AC/Lab Fee', 'category' => 'School Fee', 'is_mandatory' => true, 'is_permit_critical' => false],
        ['code' => 'SID', 'name' => 'School ID', 'category' => 'School Fee', 'is_mandatory' => true, 'is_permit_critical' => false],
        ['code' => 'INS', 'name' => 'Insurance (Group)', 'category' => 'School Fee', 'is_mandatory' => true, 'is_permit_critical' => false],
        ['code' => 'REG', 'name' => 'Registration', 'category' => 'School Fee', 'is_mandatory' => true, 'is_permit_critical' => false],
        ['code' => 'BKS', 'name' => 'Books', 'category' => 'Learning Materials', 'is_mandatory' => false, 'is_permit_critical' => false],
        ['code' => 'PEU', 'name' => 'PE Uniform', 'category' => 'Uniform', 'is_mandatory' => false, 'is_permit_critical' => false],
    ];

    /**
     * The approved collectible fees per school stage. These seven (six for
     * Junior High, which carries no computer fee) sum exactly to the totals
     * printed on the scans.
     */
    private const APPROVED_FEES = [
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

    /** Printed totals, used to verify the transcription before publishing. */
    private const APPROVED_TOTALS = ['Elementary' => '19903.25', 'Junior High School' => '21850.00'];

    /** Book prices per grade code ("BOOKS (tentative PRICE)" on the sheet). */
    private const BOOK_PRICES = [
        'G1' => '6300.00', 'G2' => '6500.00', 'G3' => '6500.00', 'G4' => '7500.00',
        'G5' => '7500.00', 'G6' => '7500.00', 'G7' => '7500.00', 'G8' => '7500.00',
        'G9' => '7500.00', 'G10' => '7500.00',
    ];

    /** PE uniform price per grade code. */
    private const PE_UNIFORM_PRICES = [
        'G1' => '800.00', 'G2' => '800.00', 'G3' => '800.00', 'G4' => '800.00',
        'G5' => '850.00', 'G6' => '850.00', 'G7' => '900.00', 'G8' => '900.00',
        'G9' => '900.00', 'G10' => '900.00',
    ];

    public function run(): void
    {
        $actor = User::query()
            ->whereHas('roles', fn ($query) => $query->where('code', 'admin'))
            ->orderBy('id')
            ->first();

        if ($actor === null) {
            throw new RuntimeException('An Admin account is required to publish approved fee schedules.');
        }

        $this->assertTranscriptionTotals();
        $feeTypes = $this->ensureFeeTypes($actor);
        $manage = app(ManageFeeScheduleAction::class);

        // Only grades that already carry a published schedule are refreshed, so
        // the seeder never invents an offering the school has not configured.
        $existing = FeeSchedule::query()
            ->where('status', 'published')
            ->whereNotNull('grade_level_id')
            ->with(['gradeLevel', 'academicYear'])
            ->get();

        if ($existing->isEmpty()) {
            $this->command?->warn('No published fee schedule found; nothing to supersede.');

            return;
        }

        foreach ($existing as $current) {
            $grade = $current->gradeLevel;
            $stage = $grade?->school_stage;

            if ($grade === null || ! isset(self::APPROVED_FEES[$stage])) {
                $this->command?->warn("Skipped {$current->name}: no approved fee set for stage \"{$stage}\".");

                continue;
            }

            $this->publishApprovedVersion($current, $grade, $stage, $feeTypes, $manage, $actor);
        }
    }

    /**
     * Guard the hand-transcribed amounts against the totals printed on the
     * scans. A typo here would bill real families the wrong amount.
     */
    private function assertTranscriptionTotals(): void
    {
        foreach (self::APPROVED_FEES as $stage => $items) {
            $sum = array_sum(array_map(fn (array $item): float => (float) $item['amount'], $items));
            $expected = (float) self::APPROVED_TOTALS[$stage];

            if (abs($sum - $expected) > 0.001) {
                throw new RuntimeException(
                    "Approved {$stage} fees total ".number_format($sum, 2).' but the approval document says '.number_format($expected, 2).'.',
                );
            }
        }
    }

    /**
     * @return array<string, FeeType>
     */
    private function ensureFeeTypes(User $actor): array
    {
        $audit = app(AuditRecorder::class);

        foreach (self::FEE_TYPES as $attributes) {
            $existing = FeeType::query()->where('code', $attributes['code'])->first();

            if ($existing !== null) {
                continue;
            }

            // Exam-permit criticality stays with Tuition and Miscellaneous;
            // widening it is a policy decision, not a transcription.
            $type = FeeType::query()->create([...$attributes, 'is_active' => true]);
            $audit->record($actor, 'billing.fee_type.created', $type, ['code' => $type->code]);
        }

        return FeeType::query()->get()->keyBy('code')->all();
    }

    /**
     * @param  array<string, FeeType>  $feeTypes
     */
    private function publishApprovedVersion(
        FeeSchedule $current,
        GradeLevel $grade,
        string $stage,
        array $feeTypes,
        ManageFeeScheduleAction $manage,
        User $actor,
    ): void {
        $year = $current->academicYear;
        $lines = $this->linesFor($stage, $grade);

        if ($this->matchesApprovedFees($current, $lines)) {
            $this->command?->line("  {$grade->name}: already on the approved amounts; left as published.");

            return;
        }

        DB::transaction(function () use ($current, $grade, $year, $lines, $feeTypes, $manage, $actor): void {
            $draft = $manage->create([
                'academic_year_id' => $current->academic_year_id,
                'grade_level_id' => $current->grade_level_id,
                'name' => $grade->name.' — Approved fees '.($year?->name ?? ''),
                'effective_on' => $year?->starts_on,
            ], $actor);

            foreach ($lines as $line) {
                $type = $feeTypes[$line['code']] ?? throw new RuntimeException("Missing fee type {$line['code']}.");
                FeeScheduleItem::query()->create([
                    'fee_schedule_id' => $draft->id,
                    'fee_type_id' => $type->id,
                    'description' => $line['description'],
                    'amount' => $line['amount'],
                    'is_required' => $line['is_required'],
                    'is_default_selected' => $line['is_default_selected'],
                ]);
            }

            $draft->forceFill([
                'status' => 'published',
                'published_by' => $actor->id,
                'published_at' => now(),
            ])->save();
            app(AuditRecorder::class)->record($actor, 'billing.fee_schedule.published', $draft, [
                'source' => 'approved_school_fees',
                'supersedes_version' => $current->version,
            ]);

            $manage->setArchived($current, true, $actor);

            $this->command?->line(sprintf(
                '  %s: published v%d (%s) and archived v%d.',
                $grade->name,
                $draft->version,
                number_format(array_sum(array_map(fn ($l) => (float) $l['amount'], $lines)), 2),
                $current->version,
            ));
        });
    }

    /**
     * The approved collectible fees, plus books and PE uniform as separately
     * priced items. Both are marked "tentative price" on the sheet and are not
     * part of the DepEd-approved total, so neither is required; books default
     * to selected because every learner needs them, while a PE uniform is often
     * carried over from the previous year.
     *
     * @return list<array{code: string, description: string, amount: string, is_required: bool, is_default_selected: bool}>
     */
    private function linesFor(string $stage, GradeLevel $grade): array
    {
        $lines = array_map(
            fn (array $item): array => [...$item, 'is_required' => true, 'is_default_selected' => true],
            self::APPROVED_FEES[$stage],
        );

        if (isset(self::BOOK_PRICES[$grade->code])) {
            $lines[] = [
                'code' => 'BKS',
                'description' => 'Books ('.$grade->name.', tentative price)',
                'amount' => self::BOOK_PRICES[$grade->code],
                'is_required' => false,
                'is_default_selected' => true,
            ];
        }

        if (isset(self::PE_UNIFORM_PRICES[$grade->code])) {
            $lines[] = [
                'code' => 'PEU',
                'description' => 'PE Uniform',
                'amount' => self::PE_UNIFORM_PRICES[$grade->code],
                'is_required' => false,
                'is_default_selected' => false,
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array{code: string, amount: string}>  $lines
     */
    private function matchesApprovedFees(FeeSchedule $schedule, array $lines): bool
    {
        $actual = $schedule->items()->with('feeType')->get()
            ->mapWithKeys(fn (FeeScheduleItem $item) => [$item->feeType->code => number_format((float) $item->amount, 2, '.', '')])
            ->sort()->all();
        $expected = collect($lines)
            ->mapWithKeys(fn (array $line) => [$line['code'] => number_format((float) $line['amount'], 2, '.', '')])
            ->sort()->all();

        return $actual == $expected;
    }
}
