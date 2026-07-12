<?php

namespace App\Modules\Enrollment\Actions;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyEnrollmentContactAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Enrollment $enrollment, string $code): Enrollment
    {
        $result = DB::transaction(function () use ($enrollment, $code): Enrollment|false {
            $record = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);

            if ($record->contact_verified_at !== null) {
                return $record;
            }

            if ($record->verification_expires_at?->isPast() || $record->verification_attempts >= 5) {
                throw ValidationException::withMessages(['verification_code' => 'The verification code can no longer be used. Ask JCA to verify the contact.']);
            }

            $record->increment('verification_attempts');
            if (! hash_equals((string) $record->verification_code_hash, hash('sha256', $code))) {
                return false;
            }

            $record->forceFill([
                'contact_verified_at' => now(),
                'verification_code_hash' => null,
                'verification_expires_at' => null,
            ])->save();
            $this->audit->record(null, 'enrollment.contact.verified', $record);

            return $record;
        });

        if ($result === false) {
            throw ValidationException::withMessages(['verification_code' => 'The verification code is invalid.']);
        }

        return $result;
    }
}
