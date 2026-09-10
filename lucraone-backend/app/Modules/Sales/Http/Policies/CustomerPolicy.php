<?php

namespace App\Modules\Sales\Http\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Sales\Domain\Models\Customer;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->canAccessTenant($customer->tenant_id) && $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->canAccessTenant($customer->tenant_id) && $this->canManage($user);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->canAccessTenant($customer->tenant_id) && $this->canManage($user);
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->canAccessTenant($customer->tenant_id) && $this->canManage($user);
    }

    private function canView(User $user): bool
    {
        return $user->hasAnyPermission(['view-customers', 'manage-customers', 'view-sales', 'manage-sales']);
    }

    private function canManage(User $user): bool
    {
        return $user->hasAnyPermission(['manage-customers', 'manage-sales']);
    }
}
