<?php

namespace App\Modules\Communications\Models;

use App\Modules\Academics\Models\Section;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementAudience extends Model
{
    protected $fillable = ['announcement_id', 'audience_type', 'role_code', 'section_id', 'student_id', 'guardian_id'];

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
