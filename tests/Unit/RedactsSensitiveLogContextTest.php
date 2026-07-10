<?php

namespace Tests\Unit;

use App\Modules\Shared\Logging\RedactsSensitiveLogContext;
use PHPUnit\Framework\TestCase;

class RedactsSensitiveLogContextTest extends TestCase
{
    public function test_sensitive_context_values_are_redacted_recursively(): void
    {
        $context = RedactsSensitiveLogContext::redact([
            'event' => 'foundation.checked',
            'password' => 'do-not-log',
            'student' => [
                'email' => 'guardian@example.test',
                'display_name' => 'Safe display value',
            ],
            'request-token' => 'do-not-log',
        ]);

        $this->assertSame('foundation.checked', $context['event']);
        $this->assertSame('[redacted]', $context['password']);
        $this->assertSame('[redacted]', $context['student']['email']);
        $this->assertSame('Safe display value', $context['student']['display_name']);
        $this->assertSame('[redacted]', $context['request-token']);
    }
}
