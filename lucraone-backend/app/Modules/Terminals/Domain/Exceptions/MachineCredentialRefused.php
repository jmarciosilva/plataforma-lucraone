<?php

namespace App\Modules\Terminals\Domain\Exceptions;

use RuntimeException;

/**
 * Emissão de credencial de máquina recusada.
 *
 * Mesmo desenho do PairingFailed: o motivo fica num campo legível pelo código,
 * e a mensagem é fixa e genérica. Nada de identificador, token, hash ou SQL na
 * mensagem — ela pode acabar em log ou numa resposta de erro.
 */
class MachineCredentialRefused extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Machine credential could not be issued.');
    }
}
