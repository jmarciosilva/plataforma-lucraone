<?php

namespace App\Modules\Authorization\Http\Policies;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\TenantContext;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canRead($user, $this->currentTenant());
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->matchesContext($branch)
            && $this->canRead($user, $branch->tenant_id)
            && $branch->company !== null;
    }

    public function create(User $user): bool
    {
        return $this->canManage($user, $this->currentTenant());
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->matchesContext($branch)
            && $this->canManage($user, $branch->tenant_id)
            && $branch->company !== null;
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch);
    }

    private function currentTenant(): ?string
    {
        $context = app(TenantContext::class);

        return $context->resolved() ? $context->id() : null;
    }

    private function matchesContext(Branch $branch): bool
    {
        return $this->currentTenant() === $branch->tenant_id;
    }

    private function canRead(User $user, ?string $tenantId): bool
    {
        // O catálogo já distingue leitura e gestão de filiais. Permissões de
        // "filiais atribuídas" não autorizam sem um modelo de atribuição real.
        return $tenantId !== null
            && $user->canAccessTenant($tenantId)
            && $user->hasAnyPermission(['view-branches', 'view-all-branches', 'manage-branches'], $tenantId);
    }

    private function canManage(User $user, ?string $tenantId): bool
    {
        return $tenantId !== null
            && $user->canAccessTenant($tenantId)
            && $user->hasPermission('manage-branches', $tenantId);
    }
}
