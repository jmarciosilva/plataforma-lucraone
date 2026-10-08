<?php

namespace App\Modules\Terminals\Application;

final readonly class TerminalProvisioningResult
{
    public function __construct(
        public string $terminalId,
        public string $tenantId,
        public string $companyId,
        public string $branchId,
        public string $installationId,
        public string $status,
    ) {}
}
