<?php

namespace App\Modules\People\Support;

use App\Models\User;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Support\Str;

final class StudentAccountIdentityMatcher
{
    public function matches(StudentProfile $student, User $account): bool
    {
        $accountTokens = $this->tokens($account->name);
        $requiredTokens = [
            ...$this->tokens($student->first_name),
            ...$this->tokens($student->last_name),
        ];

        return $requiredTokens !== []
            && collect($requiredTokens)->every(fn (string $token): bool => in_array($token, $accountTokens, true));
    }

    /** @return list<string> */
    private function tokens(string $value): array
    {
        $normalized = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();

        return $normalized === '' ? [] : explode(' ', $normalized);
    }
}
