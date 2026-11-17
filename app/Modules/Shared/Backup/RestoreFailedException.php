<?php

namespace App\Modules\Shared\Backup;

use RuntimeException;
use Throwable;

/** D-060: a live restore that did not complete; $siteDown is true only when even the automatic rollback failed. */
final class RestoreFailedException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $siteDown, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
