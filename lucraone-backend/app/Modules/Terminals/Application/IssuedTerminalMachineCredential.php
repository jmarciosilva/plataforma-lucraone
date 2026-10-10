<?php

namespace App\Modules\Terminals\Application;

use Carbon\CarbonImmutable;
use SensitiveParameter;

/**
 * Credencial de máquina recém-emitida.
 *
 * O texto puro existe apenas nesta travessia: o Sanctum guarda só o SHA-256, e
 * não há coluna no projeto que receba o valor legível. Quem recebe este objeto
 * entrega o token ao Terminal e descarta — não há como relê-lo depois.
 *
 * O __debugInfo censura o segredo porque é por var_dump/dd/log de objeto que um
 * token acaba em arquivo. Mesma proteção do IssuedTerminalPairingCode.
 */
final readonly class IssuedTerminalMachineCredential
{
    public function __construct(
        public string $terminalId,
        #[SensitiveParameter] public string $plainTextToken,
        public CarbonImmutable $expiresAt,
    ) {}

    public function __debugInfo(): array
    {
        return [
            'terminalId' => $this->terminalId,
            'plainTextToken' => '[REDACTED]',
            'expiresAt' => $this->expiresAt,
        ];
    }
}
