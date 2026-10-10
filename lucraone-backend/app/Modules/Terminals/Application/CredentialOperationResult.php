<?php

namespace App\Modules\Terminals\Application;

use App\Modules\Terminals\Domain\Models\TerminalCredentialOperation;
use SensitiveParameter;

final readonly class CredentialOperationResult
{
    public function __construct(
        public TerminalCredentialOperation $operation,
        #[SensitiveParameter] public ?string $credential,
        public string $credentialExpiresAt,
    ) {}

    public function __debugInfo(): array
    {
        return ['operation_id' => $this->operation->id, 'credential' => '[REDACTED]'];
    }
}
