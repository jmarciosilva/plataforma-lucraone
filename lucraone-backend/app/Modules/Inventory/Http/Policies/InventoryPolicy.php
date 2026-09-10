<?php

namespace App\Modules\Inventory\Http\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Inventory\Domain\Models\Inventory;

class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, Inventory $inventory): bool
    {
        return $user->canAccessTenant($inventory->tenant_id) && $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Inventory $inventory): bool
    {
        return $user->canAccessTenant($inventory->tenant_id) && $this->canManage($user);
    }

    private function canView(User $user): bool
    {
        return $user->hasAnyPermission(['view-inventory', 'manage-inventory']);
    }

    private function canManage(User $user): bool
    {
        return $user->hasPermission('manage-inventory');
    }
}
