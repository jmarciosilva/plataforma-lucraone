<?php

namespace App\Modules\Terminals\Application;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Exceptions\InvalidTerminalAssignment;
use App\Modules\Terminals\Domain\Exceptions\PairingFailed;
use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\TerminalAssignmentValidator;
use App\Modules\Terminals\Domain\TerminalStructure;

class PairingEligibility
{
    // Usado exclusivamente dentro da transação de emissão/consumo.
    public function ensure(Terminal $terminal): void
    {
        if ($terminal->status !== Terminal::STATUS_PENDING || $terminal->installation_id !== null) {
            throw new PairingFailed('terminal-not-pairable');
        }

        // Com lock: o pairing decide uma transição e não pode ver a estrutura
        // mudar no meio. A regra de "operacional" é a mesma da autenticação de
        // máquina (TerminalStructure); só a forma de carregar difere.
        $tenant = Tenant::whereKey($terminal->tenant_id)->lockForUpdate()->first();
        $company = Company::withoutGlobalScopes()->whereKey($terminal->company_id)->lockForUpdate()->first();
        $branch = Branch::withoutGlobalScopes()->whereKey($terminal->branch_id)->lockForUpdate()->first();
        if (! TerminalStructure::operacional($tenant, $company, $branch)) {
            throw new PairingFailed('structure-unavailable');
        }
        try {
            app(TerminalAssignmentValidator::class)->validate($terminal);
        } catch (InvalidTerminalAssignment) {
            throw new PairingFailed('invalid-assignment');
        }
    }
}
