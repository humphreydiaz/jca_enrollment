<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class CreateStudentProfileAction
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes, User $actor): StudentProfile
    {
        return DB::transaction(function () use ($actor, $attributes): StudentProfile {
            $student = StudentProfile::query()->create([
                'student_number' => $attributes['student_number'] ?? null,
                'first_name' => $attributes['first_name'],
                'last_name' => $attributes['last_name'],
                'middle_name' => $attributes['middle_name'] ?? null,
                'extension_name' => $attributes['extension_name'] ?? null,
                'preferred_name' => $attributes['preferred_name'] ?? null,
                'lrn' => $attributes['lrn'] ?? null,
                'sex' => $attributes['sex'] ?? null,
                'birth_date' => $attributes['birth_date'] ?? null,
                'religion' => $attributes['religion'] ?? null,
                'status' => 'active',
            ]);

            $this->auditRecorder->record($actor, 'people.student.created', $student);

            return $student;
        });
    }
}
