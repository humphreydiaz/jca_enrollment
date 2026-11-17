<?php

namespace App\Modules\Communications\Support;

/** D-052: one person an SMS can be sent to: a parent, a learner, or a staff account. */
final class SmsRecipient
{
    public function __construct(
        public readonly string $type, // guardian | student | user
        public readonly int $id,
        public readonly ?int $userId,
        public readonly string $name,
        public readonly string $mobile,
        public readonly string $publicId,
        public readonly string $detail = '',
    ) {}

    public function key(): string
    {
        return $this->type.':'.$this->publicId;
    }

    /** Same number, same person: one text per number per send. */
    public function mobileHash(): string
    {
        return hash('sha256', (string) preg_replace('/\D/', '', $this->mobile));
    }

    public function maskedMobile(): string
    {
        $digits = (string) preg_replace('/\D/', '', $this->mobile);
        $local = str_starts_with($digits, '63') ? '0'.substr($digits, 2) : $digits;

        return strlen($local) >= 8 ? substr($local, 0, 4).'•••'.substr($local, -4) : '••••';
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return ['key' => $this->key(), 'name' => $this->name, 'detail' => $this->detail, 'mobile' => $this->maskedMobile()];
    }
}
