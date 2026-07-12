<?php

namespace App\Modules\Billing\Calculations;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class DueScheduleCalculator
{
    /**
     * @param  list<string>  $quarterlyExamStarts
     * @param  list<string>  $nonBusinessDays
     * @return list<array{number:int,label:string,due_on:string,amount:string,permit_critical_amount:string}>
     */
    public function calculate(
        string $planType,
        string $tuitionAmount,
        string $oneTimeAmount,
        string $permitCriticalTuition,
        string $permitCriticalOneTime,
        string $classStartsOn,
        array $quarterlyExamStarts = [],
        array $nonBusinessDays = [],
    ): array {
        $tuition = Money::cents($tuitionAmount);
        $oneTime = Money::cents($oneTimeAmount);
        $criticalTuition = Money::cents($permitCriticalTuition);
        $criticalOneTime = Money::cents($permitCriticalOneTime);

        return match ($planType) {
            'full' => [$this->row(1, 'Full payment', $classStartsOn, $tuition + $oneTime, $criticalTuition + $criticalOneTime, $nonBusinessDays)],
            'semiannual' => $this->splitByExamDates(2, $tuition, $oneTime, $criticalTuition, $criticalOneTime, [$classStartsOn, $this->examDue($quarterlyExamStarts, 2)], $nonBusinessDays, 'Semiannual'),
            'quarterly' => $this->splitByExamDates(4, $tuition, $oneTime, $criticalTuition, $criticalOneTime, array_map(fn (int $index) => $this->examDue($quarterlyExamStarts, $index), range(0, 3)), $nonBusinessDays, 'Quarter'),
            'monthly' => $this->monthly($tuition, $oneTime, $criticalTuition, $criticalOneTime, $classStartsOn, $nonBusinessDays),
            default => throw new InvalidArgumentException('Unsupported payment plan.'),
        };
    }

    private function splitByExamDates(int $count, int $tuition, int $oneTime, int $criticalTuition, int $criticalOneTime, array $dates, array $nonBusinessDays, string $label): array
    {
        $tuitionParts = $this->split($tuition, $count);
        $criticalParts = $this->split($criticalTuition, $count);
        $rows = [];
        foreach ($tuitionParts as $index => $amount) {
            $rows[] = $this->row($index + 1, $label.' '.($index + 1), $dates[$index], $amount + ($index === 0 ? $oneTime : 0), $criticalParts[$index] + ($index === 0 ? $criticalOneTime : 0), $nonBusinessDays);
        }

        return $rows;
    }

    private function monthly(int $tuition, int $oneTime, int $criticalTuition, int $criticalOneTime, string $classStartsOn, array $nonBusinessDays): array
    {
        $tuitionParts = $this->split($tuition, 10);
        $criticalParts = $this->split($criticalTuition, 10);
        $start = CarbonImmutable::parse($classStartsOn);
        $rows = [];
        foreach ($tuitionParts as $index => $amount) {
            $date = $index === 0 ? $start : $start->addMonthsNoOverflow($index)->day(5);
            $rows[] = $this->row($index + 1, 'Monthly '.($index + 1), $date->toDateString(), $amount + ($index === 0 ? $oneTime : 0), $criticalParts[$index] + ($index === 0 ? $criticalOneTime : 0), $nonBusinessDays);
        }

        return $rows;
    }

    /** @return list<int> */
    private function split(int $amount, int $count): array
    {
        $base = intdiv($amount, $count);
        $parts = array_fill(0, $count, $base);
        $parts[$count - 1] += $amount - ($base * $count);

        return $parts;
    }

    private function examDue(array $dates, int $index): string
    {
        if (! isset($dates[$index])) {
            throw new InvalidArgumentException('The selected plan requires configured quarterly examination dates.');
        }

        return CarbonImmutable::parse($dates[$index])->subDays(10)->toDateString();
    }

    private function row(int $number, string $label, string $date, int $amount, int $critical, array $nonBusinessDays): array
    {
        $due = CarbonImmutable::parse($date);
        while ($due->isWeekend() || in_array($due->toDateString(), $nonBusinessDays, true)) {
            $due = $due->addDay();
        }

        return ['number' => $number, 'label' => $label, 'due_on' => $due->toDateString(), 'amount' => Money::decimal($amount), 'permit_critical_amount' => Money::decimal($critical)];
    }
}
