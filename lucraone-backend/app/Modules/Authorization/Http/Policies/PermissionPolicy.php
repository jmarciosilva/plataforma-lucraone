<?php

namespace App\Modules\Authorization\Http\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Authorization\Domain\Models\Permission;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->tenant_id === $permission->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create-permission');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->tenant_id === $permission->tenant_id
            && $user->hasPermission('update-permission');
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->tenant_id === $permission->tenant_id
            && $user->hasPermission('delete-permission');
    }
}
