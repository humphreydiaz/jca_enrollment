<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingSchemaTest extends TestCase
{
    use DatabaseMigrations;

    public function test_mysql_financial_schema_has_required_precision_constraints_and_indexes(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        foreach (['fee_types', 'fee_schedules', 'fee_assessments', 'assessment_items', 'payment_plans', 'payment_schedule_items', 'discount_campaigns', 'discount_awards', 'payments', 'payment_allocations', 'credit_allocations', 'payment_reversals', 'payment_reversal_allocations', 'financial_adjustments', 'refunds', 'exam_periods', 'exam_permit_calculations', 'exam_permit_overrides', 'exam_permits', 'payment_evidence'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table.' must exist.');
        }
        $amountColumn = DB::selectOne("SHOW COLUMNS FROM payments WHERE Field = 'amount'");
        $this->assertSame('decimal(12,2)', strtolower($amountColumn->Type));

        $assessmentIndexes = collect(DB::select('SHOW INDEX FROM fee_assessments'))->pluck('Key_name');
        $this->assertContains('fee_assessments_current_finalized_key_unique', $assessmentIndexes);
        $this->assertContains('fee_assessments_enrollment_id_revision_number_unique', $assessmentIndexes);
        $awardIndexes = collect(DB::select('SHOW INDEX FROM discount_awards'))->pluck('Key_name');
        $this->assertContains('discount_awards_discount_campaign_id_rank_unique', $awardIndexes);
        $paymentIndexes = collect(DB::select('SHOW INDEX FROM payments'))->pluck('Key_name');
        $this->assertContains('payments_receipt_reference_unique', $paymentIndexes);
        $this->assertContains('payments_method_method_reference_unique', $paymentIndexes);
        $permitIndexes = collect(DB::select('SHOW INDEX FROM exam_permits'))->pluck('Key_name');
        $this->assertContains('exam_permits_active_permit_key_unique', $permitIndexes);

        $foreignKeys = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())->whereIn('TABLE_NAME', ['fee_assessments', 'payments', 'payment_reversals', 'exam_permits'])->count();
        $this->assertGreaterThanOrEqual(12, $foreignKeys);
        $this->assertSame('mysql', config('database.default'));
    }
}
