<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\Identity\Actions\ContactNormalizer;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class CreateGuardianProfileAction
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes, User $actor): GuardianProfile
    {
        return DB::transaction(function () use ($actor, $attributes): GuardianProfile {
            $guardian = GuardianProfile::query()->create([
                'user_id' => isset($attributes['user_public_id'])
                    ? User::query()->where('public_id', $attributes['user_public_id'])->value('id')
                    : null,
                'first_name' => $attributes['first_name'],
                'last_name' => $attributes['last_name'],
                'middle_name' => $attributes['middle_name'] ?? null,
                'mobile' => ContactNormalizer::mobile($attributes['mobile'] ?? null),
                'email' => ContactNormalizer::email($attributes['email'] ?? null),
                'preferred_contact_method' => $attributes['preferred_contact_method'] ?? null,
                'status' => 'active',
            ]);

            $this->auditRecorder->record($actor, 'people.guardian.created', $guardian);

            return $guardian;
        });
    }
}
