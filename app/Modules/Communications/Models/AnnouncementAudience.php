<?php

namespace App\Modules\Communications\Models;

use App\Modules\Academics\Models\Section;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AnnouncementAudience extends Model
{
    protected $fillable = ['announcement_id', 'audience_type', 'role_code', 'section_id', 'student_id', 'guardian_id'];

    /**
     * A short, human-readable description of who this audience targets.
     * Shared by the admin table, the announcement detail page, and the
     * modal preview JSON so the wording stays consistent everywhere.
     */
    public function label(): string
    {
        return match ($this->audience_type) {
            'all' => 'All portals',
            'role' => [
                'guardian' => 'Guardians', 'student' => 'Students', 'teacher' => 'Teachers',
                'registrar' => 'Registrars', 'accounting' => 'Accounting',
            ][$this->role_code] ?? Str::headline((string) $this->role_code),
            'section' => 'Specific section',
            'student' => 'Specific student',
            'guardian' => 'Specific guardian',
            default => Str::headline((string) $this->audience_type),
        };
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(GuardianProfile::class);
    }
}
