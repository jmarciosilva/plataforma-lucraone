<?php

namespace App\Modules\Terminals\Http\Policies;

use App\Modules\Authorization\Http\Policies\BranchPolicy;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Terminals\Domain\Models\Terminal;

class TerminalPolicy
{
    // Fundação administrativa: reutiliza a autoridade explícita sobre filiais.
    // Permissões próprias de Terminal deverão ser decididas antes de expor UI/API.
    public function viewAny(User $user): bool
    {
        return app(BranchPolicy::class)->viewAny($user);
    }

    public function create(User $user): bool
    {
        return app(BranchPolicy::class)->create($user);
    }

    public function view(User $user, Terminal $terminal): bool
    {
        return $this->matchesContext($terminal)
            && $this->hasCoherentAssignment($terminal)
            && app(BranchPolicy::class)->view($user, $terminal->branch);
    }

    public function update(User $user, Terminal $terminal): bool
    {
        return $this->matchesContext($terminal)
            && $this->hasCoherentAssignment($terminal)
            && app(BranchPolicy::class)->update($user, $terminal->branch);
    }

    public function revoke(User $user, Terminal $terminal): bool
    {
        return $this->update($user, $terminal);
    }

    private function hasCoherentAssignment(Terminal $terminal): bool
    {
        return $terminal->branch !== null
            && $terminal->company !== null
            && $terminal->branch->tenant_id === $terminal->tenant_id
            && $terminal->branch->company_id === $terminal->company_id
            && $terminal->company->tenant_id === $terminal->tenant_id;
    }

    private function matchesContext(Terminal $terminal): bool
    {
        $context = app(TenantContext::class);

        return $context->resolved() && $context->id() === $terminal->tenant_id;
    }
}
