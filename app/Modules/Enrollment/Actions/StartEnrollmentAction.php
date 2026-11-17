<?php

namespace App\Modules\Enrollment\Actions;

use App\Models\User;
use App\Modules\Academics\Models\GradeLevel;
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
    public function __construct(
        private AuditRecorder $audit,
        private SendContactVerificationSmsAction $verificationSms,
    ) {}

    public function execute(array $data, ?User $studentApplicant = null): StartedEnrollment
    {
        if ($studentApplicant !== null && ! $studentApplicant->hasRole('student')) {
            throw ValidationException::withMessages(['application' => 'Only a Student account can own a student application.']);
        }

        $grade = GradeLevel::query()->find($data['grade_level_id']);
        if ($grade === null || ! $grade->is_active) {
            throw ValidationException::withMessages([
                'grade_level_id' => 'Select an available grade level.',
            ]);
        }

        if ($studentApplicant !== null && ! $grade->allowsStudentAccountByDefault()) {
            throw ValidationException::withMessages([
                'grade_level_id' => 'Pre-elementary and elementary applications must be completed with a parent or guardian. Ask your guardian to start the application.',
            ]);
        }

        if ($studentApplicant === null && $grade->allowsStudentAccountByDefault()) {
            throw ValidationException::withMessages([
                'grade_level_id' => 'Junior High School applications require an invited Student login.',
            ]);
        }

        $lockIdentity = $studentApplicant === null
            ? implode('|', [
                $data['lrn'] ?? '',
                Str::lower(trim($data['student_first_name'])),
                Str::lower(trim($data['student_last_name'])),
                $data['birth_date'],
                $data['academic_year_id'],
            ])
            : 'student-account|'.$studentApplicant->id.'|'.$data['academic_year_id'];
        $lockName = 'jca:enroll:'.substr(hash('sha256', $lockIdentity), 0, 40);
        $lock = DB::selectOne('SELECT GET_LOCK(?, 5) AS acquired', [$lockName]);
        if ((int) ($lock->acquired ?? 0) !== 1) {
            throw ValidationException::withMessages(['student_first_name' => 'This application is already being processed. Please try again.']);
        }

        try {
            $started = DB::transaction(function () use ($data, $studentApplicant) {
                if ($studentApplicant !== null) {
                    User::query()->lockForUpdate()->findOrFail($studentApplicant->id);
                    $hasActiveApplication = Enrollment::query()
                        ->where('student_applicant_user_id', $studentApplicant->id)
                        ->where('academic_year_id', $data['academic_year_id'])
                        ->whereNotIn('status', ['rejected', 'withdrawn', 'cancelled'])
                        ->exists();

                    if ($hasActiveApplication) {
                        throw ValidationException::withMessages([
                            'academic_year_id' => 'You already have an active application for this school year. Open My enrollment to view its status.',
                        ]);
                    }
                }

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
                    ->where(function ($query) use ($data): void {
                        if (filled($data['lrn'] ?? null)) {
                            $query->where('lrn', $data['lrn'])->orWhere(function ($identity) use ($data): void {
                                $identity->whereRaw('LOWER(first_name) = ?', [Str::lower(trim($data['student_first_name']))])
                                    ->whereRaw('LOWER(last_name) = ?', [Str::lower(trim($data['student_last_name']))])
                                    ->where(function ($birthDate) use ($data): void {
                                        $birthDate->whereDate('birth_date', $data['birth_date'])->orWhereNull('birth_date');
                                    });
                            });
                        } else {
                            $query->whereRaw('LOWER(first_name) = ?', [Str::lower(trim($data['student_first_name']))])
                                ->whereRaw('LOWER(last_name) = ?', [Str::lower(trim($data['student_last_name']))])
                                ->where(function ($birthDate) use ($data): void {
                                    $birthDate->whereDate('birth_date', $data['birth_date'])->orWhereNull('birth_date');
                                });
                        }
                    })
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

                $guardian = GuardianProfile::query()->create([
                    'first_name' => trim($data['guardian_first_name']),
                    'middle_name' => filled($data['guardian_middle_name'] ?? null) ? trim($data['guardian_middle_name']) : null,
                    'last_name' => trim($data['guardian_last_name']),
                    'extension_name' => filled($data['guardian_extension_name'] ?? null) ? trim($data['guardian_extension_name']) : null,
                    'mobile' => $mobile,
                    'email' => $email,
                ]);
                $student = StudentProfile::query()->create([
                    'lrn' => filled($data['lrn'] ?? null) ? $data['lrn'] : null,
                    'first_name' => trim($data['student_first_name']),
                    'middle_name' => filled($data['student_middle_name'] ?? null) ? trim($data['student_middle_name']) : null,
                    'last_name' => trim($data['student_last_name']),
                    'extension_name' => filled($data['extension_name'] ?? null) ? trim($data['extension_name']) : null,
                    'sex' => $data['sex'],
                    'birth_date' => $data['birth_date'],
                    'birth_place' => trim($data['birth_place']),
                    'mother_tongue' => trim($data['mother_tongue']),
                    'religion' => filled($data['religion'] ?? null) ? trim($data['religion']) : null,
                    'ip_group' => filled($data['ip_group'] ?? null) ? trim($data['ip_group']) : null,
                    'is_4ps_recipient' => (bool) $data['is_4ps_recipient'],
                    'four_ps_household_id' => (bool) $data['is_4ps_recipient'] ? trim($data['four_ps_household_id']) : null,
                    'is_learner_with_disability' => (bool) $data['is_learner_with_disability'],
                    'disability_type' => (bool) $data['is_learner_with_disability'] ? $data['disability_type'] : null,
                    'current_address' => trim($data['current_address']),
                    'permanent_address' => (bool) $data['same_as_current_address'] ? trim($data['current_address']) : trim($data['permanent_address']),
                    'father_first_name' => filled($data['father_first_name'] ?? null) ? trim($data['father_first_name']) : null,
                    'father_middle_name' => filled($data['father_middle_name'] ?? null) ? trim($data['father_middle_name']) : null,
                    'father_last_name' => filled($data['father_last_name'] ?? null) ? trim($data['father_last_name']) : null,
                    'father_extension_name' => filled($data['father_extension_name'] ?? null) ? trim($data['father_extension_name']) : null,
                    'mother_first_name' => filled($data['mother_first_name'] ?? null) ? trim($data['mother_first_name']) : null,
                    'mother_middle_name' => filled($data['mother_middle_name'] ?? null) ? trim($data['mother_middle_name']) : null,
                    'mother_maiden_last_name' => filled($data['mother_maiden_last_name'] ?? null) ? trim($data['mother_maiden_last_name']) : null,
                    'mother_extension_name' => filled($data['mother_extension_name'] ?? null) ? trim($data['mother_extension_name']) : null,
                ]);
                $resumeToken = Str::random(64);
                $verificationCode = (string) random_int(100000, 999999);
                $record = Enrollment::query()->create([
                    'application_number' => 'JCA-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
                    'student_id' => $student->id,
                    'academic_year_id' => $data['academic_year_id'],
                    'grade_level_id' => $data['grade_level_id'],
                    'applicant_guardian_id' => $guardian->id,
                    'student_applicant_user_id' => $studentApplicant?->id,
                    'student_category' => $data['student_category'],
                    'guardian_relationship' => $data['guardian_relationship'],
                    'previous_school_name' => filled($data['previous_school_name'] ?? null) ? trim($data['previous_school_name']) : null,
                    'previous_school_address' => filled($data['previous_school_address'] ?? null) ? trim($data['previous_school_address']) : null,
                    'status' => 'draft',
                    'active_enrollment_key' => $student->id.':'.$data['academic_year_id'],
                    'active_student_application_key' => $studentApplicant === null ? null : $studentApplicant->id.':'.$data['academic_year_id'],
                    'resume_token_hash' => hash('sha256', $resumeToken),
                    'verification_code_hash' => hash('sha256', $verificationCode),
                    'verification_expires_at' => now()->addMinutes(15),
                    'duplicate_review_status' => $candidate || $guardianCandidate ? 'pending' : 'clear',
                    'potential_duplicate_student_id' => $candidate?->id,
                    'potential_duplicate_guardian_id' => $guardianCandidate?->id,
                ]);
                $this->audit->record($studentApplicant, 'enrollment.draft.started', $record);

                return new StartedEnrollment($record, $resumeToken, $verificationCode);
            });
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }

        if ($started->enrollment->guardian->email !== null) {
            Mail::to($started->enrollment->guardian->email)->queue(new EnrollmentContactVerificationMail($started->enrollment->application_number, $started->verificationCode));
        }

        if ($started->enrollment->guardian->mobile !== null) {
            $this->verificationSms->execute($started->enrollment, $started->enrollment->guardian->mobile, $started->verificationCode);
        }

        return $started;
    }
}
