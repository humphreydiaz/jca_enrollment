<?php

namespace App\Modules\Enrollment\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class StaffVerifyEnrollmentContactAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Enrollment $enrollment, User $actor): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $actor): Enrollment {
            $record = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            $record->update(['contact_verified_at' => now(), 'verification_code_hash' => null, 'verification_expires_at' => null]);
            $this->audit->record($actor, 'enrollment.contact.staff_verified', $record);

            return $record;
        });
    }
}
