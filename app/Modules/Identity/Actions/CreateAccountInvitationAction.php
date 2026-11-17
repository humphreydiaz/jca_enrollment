<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Models\AccountInvitation;
use App\Modules\Identity\Support\InvitationCode;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class CreateAccountInvitationAction
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(User $user, string $channel, string $sentTo, ?User $actor, string $purpose = 'account_setup'): CreatedInvitation
    {
        return DB::transaction(function () use ($actor, $channel, $purpose, $sentTo, $user): CreatedInvitation {
            AccountInvitation::query()
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoked_by' => $actor?->id,
                ]);

            // A grouped, unambiguous 80-bit code is practical to type while
            // remaining strong enough for a short-lived, rate-limited invitation.
            $token = InvitationCode::generate();
            $invitation = AccountInvitation::query()->create([
                'user_id' => $user->id,
                'purpose' => $purpose,
                'channel' => $channel,
                'sent_to' => $sentTo,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(3),
                'created_by' => $actor?->id,
            ]);

            $this->auditRecorder->record($actor, "identity.{$purpose}.invitation.created", $user, [
                'channel' => $channel,
            ]);

            return new CreatedInvitation($invitation, $token);
        });
    }
}
