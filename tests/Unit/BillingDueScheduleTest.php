<?php

namespace Tests\Unit;

use App\Modules\Billing\Calculations\DueScheduleCalculator;
use App\Modules\Billing\Calculations\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BillingDueScheduleTest extends TestCase
{
    public function test_all_plans_use_exact_centavos_and_business_days(): void
    {
        $calculator = new DueScheduleCalculator;
        $full = $calculator->calculate('full', '100.01', '20.00', '100.01', '10.00', '2026-06-06', [], ['2026-06-08']);
        $this->assertSame('2026-06-09', $full[0]['due_on']);
        $this->assertSame('120.01', $full[0]['amount']);

        $monthly = $calculator->calculate('monthly', '100.01', '20.00', '100.01', '10.00', '2026-06-01');
        $this->assertCount(10, $monthly);
        $this->assertSame('30.00', $monthly[0]['amount']);
        $this->assertSame('10.01', $monthly[9]['amount']);
        $this->assertSame('120.01', Money::sum(collect($monthly)->pluck('amount')));

        $exams = ['2026-08-20', '2026-10-22', '2027-01-21', '2027-03-18'];
        $quarterly = $calculator->calculate('quarterly', '100.01', '20.00', '100.01', '10.00', '2026-06-01', $exams);
        $this->assertSame('2026-08-10', $quarterly[0]['due_on']);
        $this->assertSame('45.00', $quarterly[0]['amount']);
        $this->assertSame('25.01', $quarterly[3]['amount']);

        $semiannual = $calculator->calculate('semiannual', '100.01', '20.00', '100.01', '10.00', '2026-06-01', $exams);
        $this->assertSame('2027-01-11', $semiannual[1]['due_on']);
        $this->assertSame('50.01', $semiannual[1]['amount']);
    }

    public function test_exam_based_plan_requires_configured_periods(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DueScheduleCalculator)->calculate('quarterly', '100.00', '0.00', '100.00', '0.00', '2026-06-01');
    }
}
