<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Support\RandomThreeDigitSuffix;
use App\Modules\People\Support\StaffNumber;
use App\Modules\Shared\Audit\AuditRecorder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class IssueStaffNumberAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly RandomThreeDigitSuffix $suffix,
    ) {}

    public function execute(StaffProfile $staff, CarbonInterface $employedOn, ?User $actor = null): string
    {
        $yearCode = StaffNumber::yearCode($employedOn);

        return DB::transaction(function () use ($actor, $employedOn, $staff, $yearCode): string {
            $record = StaffProfile::query()->lockForUpdate()->findOrFail($staff->id);

            if (filled($record->employee_number)) {
                return $record->employee_number;
            }

            DB::table('identifier_namespaces')->insertOrIgnore([
                'identifier_type' => 'staff',
                'year_code' => $yearCode,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('identifier_namespaces')
                ->where('identifier_type', 'staff')
                ->where('year_code', $yearCode)
                ->lockForUpdate()
                ->first();

            for ($attempt = 0; $attempt < 900; $attempt++) {
                $number = StaffNumber::format($yearCode, $this->suffix->generate());

                if (! StaffProfile::query()->where('employee_number', $number)->exists()) {
                    $record->forceFill([
                        'employee_number' => $number,
                        'active_from' => $record->active_from ?? $employedOn->toDateString(),
                    ])->save();
                    $this->audit->record($actor, 'people.staff.number_issued', $record, [
                        'employee_number' => $number,
                        'employment_year' => $yearCode,
                    ]);

                    return $number;
                }
            }

            throw new \RuntimeException("The {$yearCode}-JCA staff-number namespace is exhausted.");
        });
    }
}
