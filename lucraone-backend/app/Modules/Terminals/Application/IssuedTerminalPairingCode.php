<?php

namespace App\Modules\Terminals\Application;

use Carbon\CarbonImmutable;
use SensitiveParameter;

final readonly class IssuedTerminalPairingCode
{
    public function __construct(
        public string $pairingId,
        #[SensitiveParameter] public string $code,
        public CarbonImmutable $expiresAt,
    ) {}

    public function __debugInfo(): array
    {
        return ['pairingId' => $this->pairingId, 'code' => '[REDACTED]', 'expiresAt' => $this->expiresAt];
    }
}
