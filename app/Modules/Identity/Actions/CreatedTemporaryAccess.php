<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Carbon;

final readonly class CreatedTemporaryAccess
{
    public function __construct(
        public User $user,
        public string $temporaryPassword,
        public Carbon $expiresAt,
    ) {}
}
