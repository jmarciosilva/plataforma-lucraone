<?php

namespace App\Modules\Menu\Http\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Menu\Domain\Models\ModifierGroup;

class ModifierGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['view-products', 'manage-products']);
    }

    public function view(User $user, ModifierGroup $group): bool
    {
        return $user->canAccessTenant($group->tenant_id)
            && $user->hasAnyPermission(['view-products', 'manage-products']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-products');
    }

    public function update(User $user, ModifierGroup $group): bool
    {
        return $user->canAccessTenant($group->tenant_id)
            && $user->hasPermission('manage-products');
    }

    public function delete(User $user, ModifierGroup $group): bool
    {
        return $user->canAccessTenant($group->tenant_id)
            && $user->hasPermission('manage-products');
    }
}
