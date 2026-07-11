<?php

namespace App\Modules\Enrollment\Actions;

use App\Modules\Enrollment\Mail\EnrollmentContactVerificationMail;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\ContactNormalizer;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StartEnrollmentAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(array $data): StartedEnrollment
    {
        $lockName = 'jca:enroll:'.substr(hash('sha256', implode('|', [
            Str::lower(trim($data['student_first_name'])),
            Str::lower(trim($data['student_last_name'])),
            $data['birth_date'],
            $data['academic_year_id'],
        ])), 0, 40);
        $lock = DB::selectOne('SELECT GET_LOCK(?, 5) AS acquired', [$lockName]);
        if ((int) ($lock->acquired ?? 0) !== 1) {
            throw ValidationException::withMessages(['student_first_name' => 'This application is already being processed. Please try again.']);
        }

        try {
            $started = DB::transaction(function () use ($data) {
                $email = ContactNormalizer::email($data['guardian_email'] ?? null);
                $mobile = ContactNormalizer::mobile($data['guardian_mobile'] ?? null);
                if ($email === null && $mobile === null) {
                    throw ValidationException::withMessages(['guardian_mobile' => 'Enter a valid email address or mobile number.']);
                }
                $guardianCandidate = GuardianProfile::query()
                    ->where(function ($query) use ($email, $mobile): void {
                        if ($email !== null) {
                            $query->orWhereRaw('LOWER(email) = ?', [$email]);
                        }
                        if ($mobile !== null) {
                            $query->orWhere('mobile', $mobile);
                        }
                    })
                    ->lockForUpdate()
                    ->first();
                $candidates = StudentProfile::query()
                    ->whereRaw('LOWER(first_name) = ?', [Str::lower(trim($data['student_first_name']))])
                    ->whereRaw('LOWER(last_name) = ?', [Str::lower(trim($data['student_last_name']))])
                    ->whereDate('birth_date', $data['birth_date'])
                    ->lockForUpdate()
                    ->get();
                $candidate = $candidates->first();

                if ($candidates->isNotEmpty() && Enrollment::query()
                    ->whereIn('student_id', $candidates->modelKeys())
                    ->where('academic_year_id', $data['academic_year_id'])
                    ->whereNotIn('status', ['rejected', 'withdrawn', 'cancelled'])
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'student_first_name' => 'An application for these student details and school year already exists. Contact JCA to recover it.',
                    ]);
                }

                $guardian = GuardianProfile::query()->create(['first_name' => trim($data['guardian_first_name']), 'last_name' => trim($data['guardian_last_name']), 'mobile' => $mobile, 'email' => $email]);
                $student = StudentProfile::query()->create(['first_name' => $data['student_first_name'], 'last_name' => $data['student_last_name'], 'birth_date' => $data['birth_date'] ?? null]);
                $resumeToken = Str::random(64);
                $verificationCode = (string) random_int(100000, 999999);
                $record = Enrollment::query()->create([
                    'application_number' => 'JCA-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
                    'student_id' => $student->id,
                    'academic_year_id' => $data['academic_year_id'],
                    'grade_level_id' => $data['grade_level_id'],
                    'applicant_guardian_id' => $guardian->id,
                    'student_category' => $data['student_category'],
                    'status' => 'draft',
                    'active_enrollment_key' => $student->id.':'.$data['academic_year_id'],
                    'resume_token_hash' => hash('sha256', $resumeToken),
                    'verification_code_hash' => hash('sha256', $verificationCode),
                    'verification_expires_at' => now()->addMinutes(15),
                    'duplicate_review_status' => $candidate || $guardianCandidate ? 'pending' : 'clear',
                    'potential_duplicate_student_id' => $candidate?->id,
                    'potential_duplicate_guardian_id' => $guardianCandidate?->id,
                ]);
                $this->audit->record(null, 'enrollment.draft.started', $record);

                return new StartedEnrollment($record, $resumeToken, $verificationCode);
            });
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }

        if ($started->enrollment->guardian->email !== null) {
            Mail::to($started->enrollment->guardian->email)->queue(new EnrollmentContactVerificationMail($started->enrollment->application_number, $started->verificationCode));
        }

        return $started;
    }
}
