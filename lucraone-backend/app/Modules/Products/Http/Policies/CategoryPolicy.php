<?php

namespace App\Modules\Products\Http\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Category;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canViewProducts($user);
    }

    public function view(User $user, Category $category): bool
    {
        return $user->canAccessTenant($category->tenant_id)
            && $this->canViewProducts($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageProducts($user);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->canAccessTenant($category->tenant_id)
            && $this->canManageProducts($user);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->canAccessTenant($category->tenant_id)
            && $this->canManageProducts($user);
    }

    public function restore(User $user, Category $category): bool
    {
        return $user->canAccessTenant($category->tenant_id)
            && $this->canManageProducts($user);
    }

    private function canViewProducts(User $user): bool
    {
        return $user->hasAnyPermission(['view-products', 'manage-products', 'create-role']);
    }

    private function canManageProducts(User $user): bool
    {
        return $user->hasAnyPermission(['manage-products', 'create-role']);
    }
}
