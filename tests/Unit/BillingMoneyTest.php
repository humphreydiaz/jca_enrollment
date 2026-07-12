<?php

namespace Tests\Unit;

use App\Modules\Billing\Calculations\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BillingMoneyTest extends TestCase
{
    public function test_exact_decimal_conversion_and_sum(): void
    {
        $this->assertSame(100001, Money::cents('1000.01'));
        $this->assertSame('1000.01', Money::decimal(100001));
        $this->assertSame('0.30', Money::sum(['0.10', '0.20']));
    }

    public function test_money_rejects_more_than_two_decimal_places(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::cents('1.001');
    }
}
