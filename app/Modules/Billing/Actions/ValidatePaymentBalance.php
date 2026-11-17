<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Calculations\BalanceCalculator;
use App\Modules\Billing\Calculations\Money;
use App\Modules\Billing\Models\FeeAssessment;
use Illuminate\Validation\ValidationException;

final class ValidatePaymentBalance
{
    public function __construct(
        private BalanceCalculator $calculator
    ) {}

    public function execute(
        FeeAssessment $assessment,
        string $amount
    ): void {
        if ($assessment->status !== 'finalized') {
            throw ValidationException::withMessages([
                'assessment' => 'Payments require a finalized assessment.',
            ]);
        }

        $balance = $this->calculator->calculate($assessment);

        $remaining = Money::cents((string) $balance['due']);
        $requested = Money::cents($amount);

        if ($requested <= 0 || $requested > $remaining) {
            throw ValidationException::withMessages([
                'amount' => 'Payment must be greater than zero and must not exceed the remaining balance of PHP '
                    . number_format($remaining / 100, 2) . '.',
            ]);
        }
    }
}