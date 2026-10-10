<?php

namespace App\Modules\Terminals\Domain\Exceptions;

use RuntimeException;

final class CredentialLifecycleFailed extends RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly int $httpStatus)
    {
        parent::__construct('Terminal credential operation refused.');
    }
}
