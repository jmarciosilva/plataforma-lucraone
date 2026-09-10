<?php

namespace App\Modules\Companies\Http\Policies;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canViewCompanies($user);
    }

    public function view(User $user, Company $company): bool
    {
        return $user->canAccessTenant($company->tenant_id)
            && $this->canViewCompanies($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageCompanies($user);
    }

    public function update(User $user, Company $company): bool
    {
        return $user->canAccessTenant($company->tenant_id)
            && $this->canManageCompanies($user);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->canAccessTenant($company->tenant_id)
            && $this->canManageCompanies($user);
    }

    public function restore(User $user, Company $company): bool
    {
        return $user->canAccessTenant($company->tenant_id)
            && $this->canManageCompanies($user);
    }

    private function canViewCompanies(User $user): bool
    {
        return $user->hasAnyPermission(['view-companies', 'manage-companies']);
    }

    private function canManageCompanies(User $user): bool
    {
        return $user->hasPermission('manage-companies');
    }
}
