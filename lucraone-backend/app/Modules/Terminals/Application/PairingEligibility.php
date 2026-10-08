<?php

namespace App\Modules\Terminals\Application;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Exceptions\InvalidTerminalAssignment;
use App\Modules\Terminals\Domain\Exceptions\PairingFailed;
use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\TerminalAssignmentValidator;

class PairingEligibility
{
    // Usado exclusivamente dentro da transação de emissão/consumo.
    public function ensure(Terminal $terminal): void
    {
        if ($terminal->status !== Terminal::STATUS_PENDING || $terminal->installation_id !== null) {
            throw new PairingFailed('terminal-not-pairable');
        }

        $tenant = Tenant::whereKey($terminal->tenant_id)->lockForUpdate()->first();
        $company = Company::withoutGlobalScopes()->whereKey($terminal->company_id)->lockForUpdate()->first();
        $branch = Branch::withoutGlobalScopes()->whereKey($terminal->branch_id)->lockForUpdate()->first();
        if ($tenant === null || ! $tenant->isActive()
            || $company === null || ! $company->isActive()
            || $branch === null || ! $branch->isActive()) {
            throw new PairingFailed('structure-unavailable');
        }
        try {
            app(TerminalAssignmentValidator::class)->validate($terminal);
        } catch (InvalidTerminalAssignment) {
            throw new PairingFailed('invalid-assignment');
        }
    }
}
