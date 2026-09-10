<?php

namespace App\Modules\Tenancy\Http\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageTenants($user);
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $this->canManageTenants($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageTenants($user);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->canManageTenants($user);
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $this->canManageTenants($user);
    }

    public function restore(User $user, Tenant $tenant): bool
    {
        return $this->canManageTenants($user);
    }

    private function canManageTenants(User $user): bool
    {
        return $user->isPlatformAdmin();
    }
}
