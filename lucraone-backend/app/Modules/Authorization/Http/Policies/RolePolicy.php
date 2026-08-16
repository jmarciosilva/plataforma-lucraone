<?php

namespace App\Modules\Authorization\Http\Policies;

use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['view-roles', 'update-role', 'create-role']);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->canAccessTenant($role->tenant_id)
            && $user->hasAnyPermission(['view-roles', 'update-role', 'create-role']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create-role');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->canAccessTenant($role->tenant_id)
            && $user->hasPermission('update-role');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->canAccessTenant($role->tenant_id)
            && $user->hasPermission('delete-role')
            && ! $this->isBuiltInRole($role);
    }

    private function isBuiltInRole(Role $role): bool
    {
        return in_array($role->name, ['admin', 'manager', 'user', 'viewer']);
    }
}
