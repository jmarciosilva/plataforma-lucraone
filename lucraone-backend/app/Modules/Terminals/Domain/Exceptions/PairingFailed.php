<?php

namespace App\Modules\Terminals\Domain\Exceptions;

use RuntimeException;

class PairingFailed extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        // Não incluir entrada, hash, UUID, SQL ou exceção anterior na mensagem.
        parent::__construct('Pairing could not be completed.');
    }
}
