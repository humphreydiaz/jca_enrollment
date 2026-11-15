<?php

namespace App\Modules\People\Support;

/**
 * Learner exit statuses and the SF10 (formerly Form 137) release methods.
 *
 * - Transfer out: the learner's SF10 is released school-to-school. It is either
 *   delivered/sent or mailed to the receiving school; when the receiving school
 *   is too far, the SF10 is hand-carried by the student or guardian.
 * - Dropped: the drop itself is encoded directly in DepEd LIS. The JCA system
 *   only mirrors that the LIS drop has been recorded so the learner is no
 *   longer shown as Enrolled.
 */
final class StudentExitOptions
{
    public const TRANSFERRED_OUT = 'transferred_out';

    public const DROPPED = 'dropped';

    public const GRADUATED = 'graduated';

    /**
     * Learner record statuses that mean the learner has left the school.
     *
     * @var array<string, string>
     */
    public const EXIT_STATUSES = [
        self::TRANSFERRED_OUT => 'Transferred Out',
        self::DROPPED => 'Dropped',
        self::GRADUATED => 'Graduated',
    ];

    /**
     * @var array<string, string>
     */
    public const SF10_RELEASE_METHODS = [
        'school_to_school' => 'Sent school-to-school (delivered to the receiving school)',
        'mail' => 'Mailed to the receiving school',
        'hand_carried' => 'Hand-carried by the student or guardian (receiving school is far)',
    ];

    public static function exitLabel(?string $status): ?string
    {
        return $status === null ? null : (self::EXIT_STATUSES[$status] ?? null);
    }

    public static function isExited(?string $status): bool
    {
        return $status !== null && array_key_exists($status, self::EXIT_STATUSES);
    }

    public static function releaseMethodLabel(string $method): string
    {
        return self::SF10_RELEASE_METHODS[$method] ?? $method;
    }
}
