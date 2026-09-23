<?php

namespace App\Modules\Communications\Contracts;

final readonly class SmsGatewayResult
{
    public function __construct(
        public string $status,
        public ?string $providerReference = null,
        public ?string $failureCode = null,
        public ?string $providerStatus = null,
    ) {}

    public function accepted(): bool
    {
        return in_array($this->status, ['accepted', 'logged'], true);
    }
}
