<?php

namespace App\Modules\Terminals\Domain;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Exceptions\InvalidTerminalAssignment;
use App\Modules\Terminals\Domain\Models\Terminal;

class TerminalAssignmentValidator
{
    public function validate(Terminal $terminal): void
    {
        // Contexto de consulta não substitui os IDs explícitos da entidade.
        $company = Company::withoutGlobalScopes()->find($terminal->company_id);
        $branch = Branch::withoutGlobalScopes()->find($terminal->branch_id);
        if (! Tenant::whereKey($terminal->tenant_id)->exists()
            || $company === null || $branch === null
            || $company->tenant_id !== $terminal->tenant_id
            || $branch->tenant_id !== $terminal->tenant_id
            || $branch->company_id !== $terminal->company_id) {
            throw new InvalidTerminalAssignment('Terminal requires a Company and Branch belonging to the same Tenant and Company.');
        }

        if (! is_string($terminal->name) || trim($terminal->name) === '' || mb_strlen($terminal->name) > 255) {
            throw new InvalidTerminalAssignment('Terminal requires an administrative name of up to 255 characters.');
        }

        if (! in_array($terminal->status, Terminal::STATUSES, true)) {
            throw new InvalidTerminalAssignment('Invalid Terminal status.');
        }

        if ($terminal->exists && $terminal->getRawOriginal('status') === Terminal::STATUS_REVOKED
            && $terminal->status !== Terminal::STATUS_REVOKED) {
            throw new InvalidTerminalAssignment('A REVOKED Terminal cannot be reactivated.');
        }

        if ($terminal->installation_id !== null) {
            if (! is_string($terminal->installation_id)) {
                throw new InvalidTerminalAssignment('Installation ID must be a UUID v4.');
            }
            $terminal->installation_id = InstallationId::normalize($terminal->installation_id);
        }

        if ($terminal->exists && $terminal->getRawOriginal('installation_id') !== null
            && $terminal->installation_id !== $terminal->getRawOriginal('installation_id')) {
            throw new InvalidTerminalAssignment('A bound Installation ID cannot be changed or cleared.');
        }

        if ($terminal->status === Terminal::STATUS_ACTIVE && $terminal->installation_id === null) {
            throw new InvalidTerminalAssignment('An ACTIVE Terminal requires an Installation ID; this does not authenticate it.');
        }
    }
}
