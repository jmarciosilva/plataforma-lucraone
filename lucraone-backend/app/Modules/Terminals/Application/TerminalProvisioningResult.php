<?php

namespace App\Modules\Terminals\Application;

use Carbon\CarbonImmutable;
use SensitiveParameter;

/**
 * Resultado do provisionamento de um Terminal pelo pairing.
 *
 * Desde o PDV-BE-04 carrega também a credencial de máquina: o consumo do código
 * ativa o Terminal e emite a credencial na mesma transação, para que o endpoint
 * do PDV-BE-05 tenha tudo o que precisa devolver sem orquestrar nada.
 *
 * `credential` é o texto puro, e só existe nesta travessia — o banco guarda
 * apenas o SHA-256 do Sanctum. O __debugInfo censura o valor porque é por
 * var_dump/dd/log de objeto que um segredo acaba em arquivo; o PDV-BE-05
 * mapeia os campos para JSON manualmente, em vez de serializar o objeto.
 */
final readonly class TerminalProvisioningResult
{
    public function __construct(
        public string $terminalId,
        public string $tenantId,
        public string $companyId,
        public string $branchId,
        public string $installationId,
        public string $status,
        #[SensitiveParameter] public string $credential,
        public CarbonImmutable $credentialExpiresAt,
    ) {}

    public function __debugInfo(): array
    {
        return [
            'terminalId' => $this->terminalId,
            'tenantId' => $this->tenantId,
            'companyId' => $this->companyId,
            'branchId' => $this->branchId,
            'installationId' => $this->installationId,
            'status' => $this->status,
            'credential' => '[REDACTED]',
            'credentialExpiresAt' => $this->credentialExpiresAt,
        ];
    }
}
