<?php

namespace App\Modules\Communications\Actions;

use Illuminate\Validation\ValidationException;

/**
 * School-wide messages stay general: an announcement (portal or SMS) may not carry confidential
 * support, document, or financial detail, because it reaches many people and shared phones.
 */
class SafeMessageContent
{
    private const CONFIDENTIAL = '/\b(therapy|diagnos(?:is|ed)|medical|assessment|document|balance|receipt|payment)\b/i';

    /** @param  array<string, string>  $fields  field name => text */
    public function guard(array $fields): void
    {
        $errors = [];
        foreach ($fields as $field => $text) {
            if ($text !== '' && preg_match(self::CONFIDENTIAL, $text)) {
                $errors[$field] = 'Announcements must not contain confidential support, document, or financial detail (for example payment, balance, receipt, assessment, medical). Keep them general, or message the person directly.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
