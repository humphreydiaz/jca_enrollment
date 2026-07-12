<?php

namespace Tests\Feature;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Billing\Actions\AwardDiscountAction;
use App\Modules\Billing\Actions\NextFinancialReferenceAction;
use App\Modules\Billing\Models\DiscountCampaign;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BillingConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_mysql_locks_serialize_discount_slots_and_yearly_references(): void
    {
        $year = AcademicYear::query()->create(['code' => 'LOCK-BILL', 'name' => 'Fictional lock year', 'starts_on' => today()->addMonth(), 'ends_on' => today()->addYear(), 'status' => 'open']);
        $grade = GradeLevel::query()->create(['code' => 'LOCK-G', 'name' => 'Fictional grade', 'sequence' => 1, 'is_active' => true]);
        $campaign = DiscountCampaign::query()->create(['academic_year_id' => $year->id, 'code' => 'LOCK-ONE', 'name' => 'Fictional lock campaign', 'value_type' => 'fixed', 'value_amount' => '1000.00', 'slot_limit' => 1, 'starts_on' => today()->subDay(), 'ends_on' => today()->addDay(), 'is_active' => true, 'is_development_fixture' => true]);
        $first = $this->enrollment($year, $grade, 'FIRST');
        $second = $this->enrollment($year, $grade, 'SECOND');

        config(['database.connections.billing_lock_holder' => config('database.connections.mysql')]);
        $holder = DB::connection('billing_lock_holder');
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        $holder->beginTransaction();
        $holder->select('SELECT id FROM discount_campaigns WHERE id = ? FOR UPDATE', [$campaign->id]);
        try {
            app(AwardDiscountAction::class)->execute($campaign, $first);
            $this->fail('Award allocation must wait for the campaign row lock.');
        } catch (QueryException) {
            $this->assertDatabaseCount('discount_awards', 0);
        } finally {
            $holder->rollBack();
        }
        $firstAward = app(AwardDiscountAction::class)->execute($campaign, $second);
        $this->assertSame($first->id, $firstAward?->enrollment_id);
        $this->assertNull(app(AwardDiscountAction::class)->execute($campaign, $second));
        $this->assertDatabaseCount('discount_awards', 1);

        $sequenceYear = (int) now()->format('Y');
        DB::table('financial_sequences')->insert(['sequence_type' => 'receipt', 'sequence_year' => $sequenceYear, 'last_value' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $holder->beginTransaction();
        $holder->select('SELECT id FROM financial_sequences WHERE sequence_type = ? AND sequence_year = ? FOR UPDATE', ['receipt', $sequenceYear]);
        try {
            app(NextFinancialReferenceAction::class)->execute('receipt', 'JCA-REC');
            $this->fail('Receipt sequencing must wait for the yearly sequence row lock.');
        } catch (QueryException) {
            $this->assertSame(0, (int) DB::table('financial_sequences')->where('sequence_type', 'receipt')->value('last_value'));
        } finally {
            $holder->rollBack();
            DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
            DB::disconnect('billing_lock_holder');
            DB::disconnect('mysql');
            DB::reconnect('mysql');
        }
        $firstReference = app(NextFinancialReferenceAction::class)->execute('receipt', 'JCA-REC');
        $secondReference = app(NextFinancialReferenceAction::class)->execute('receipt', 'JCA-REC');
        $this->assertNotSame($firstReference, $secondReference);
        $this->assertMatchesRegularExpression('/^JCA-REC-\d{4}-000001$/', $firstReference);
        $this->assertMatchesRegularExpression('/^JCA-REC-\d{4}-000002$/', $secondReference);
    }

    private function enrollment(AcademicYear $year, GradeLevel $grade, string $suffix): Enrollment
    {
        $student = StudentProfile::query()->create(['first_name' => 'Fictional', 'last_name' => $suffix]);
        $guardian = GuardianProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Guardian '.$suffix]);

        return Enrollment::query()->create(['application_number' => 'LOCK-'.$suffix, 'student_id' => $student->id, 'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'applicant_guardian_id' => $guardian->id, 'student_category' => 'regular', 'status' => 'approved', 'approved_at' => '2026-07-01 09:00:00', 'active_enrollment_key' => $student->id.':'.$year->id]);
    }
}
