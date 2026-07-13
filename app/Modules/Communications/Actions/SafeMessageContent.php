<?php

namespace App\Modules\Communications\Actions;

use Illuminate\Validation\ValidationException;

class SafeMessageContent
{
    /** @var list<string> */
    private const ALLOWED = ['school_name', 'status', 'action_url', 'announcement_title', 'announcement_body'];

    public function validate(string $content): void
    {
        foreach (preg_match_all('/{{\s*([a-z_]+)\s*}}/i', $content, $matches) ? $matches[1] : [] as $placeholder) {
            if (! in_array($placeholder, self::ALLOWED, true)) {
                throw ValidationException::withMessages(['content' => 'This template contains an unsupported placeholder.']);
            }
        }
        if (preg_match('/\b(therapy|diagnos(?:is|ed)|medical|assessment|document|balance|receipt|payment)\b/i', $content)) {
            throw ValidationException::withMessages(['content' => 'Messages must not contain confidential support, document, or financial detail.']);
        }
    }

    /** @param array<string, string> $values */
    public function render(string $content, array $values): string
    {
        $this->validate($content);

        return preg_replace_callback('/{{\s*([a-z_]+)\s*}}/i', fn (array $match): string => $values[$match[1]] ?? '', $content) ?? $content;
    }
}
