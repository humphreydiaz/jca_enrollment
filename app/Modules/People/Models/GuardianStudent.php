<?php

namespace App\Modules\People\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuardianStudent extends Model
{
    /**
     * @var string
     */
    protected $table = 'guardian_student';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'guardian_id',
        'student_id',
        'relationship_type',
        'is_primary_contact',
        'may_receive_sms',
        'may_view_financials',
        'may_manage_enrollment',
        'has_legal_authority',
        'verified_by',
        'verified_at',
        'effective_from',
        'ended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary_contact' => 'boolean',
            'may_receive_sms' => 'boolean',
            'may_view_financials' => 'boolean',
            'may_manage_enrollment' => 'boolean',
            'has_legal_authority' => 'boolean',
            'verified_at' => 'datetime',
            'effective_from' => 'date',
            'ended_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<GuardianProfile, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(GuardianProfile::class, 'guardian_id');
    }

    /**
     * @return BelongsTo<StudentProfile, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
