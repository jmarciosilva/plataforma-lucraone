<?php

namespace App\Modules\Authorization\Application\Traits;

use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Authorization\Domain\Models\Permission;

trait HasRole
{
    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'user_role',
            'user_id',
            'role_id'
        )->where('user_role.tenant_id', $this->tenant_id)
            ->withTimestamps();
    }

    public function hasRole($role): bool
    {
        if (is_string($role)) {
            return $this->roles()->where('name', $role)->exists();
        }

        return $this->roles()->where('id', $role->id)->exists();
    }

    public function hasAnyRole($roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    public function hasAllRoles($roles): bool
    {
        foreach ($roles as $role) {
            if (!$this->hasRole($role)) {
                return false;
            }
        }
        return true;
    }

    public function hasPermission($permission): bool
    {
        if (is_string($permission)) {
            return $this->roles()
                ->whereHas('permissions', function ($query) use ($permission) {
                    $query->where('name', $permission);
                })->exists();
        }

        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('id', $permission->id);
            })->exists();
    }

    public function hasAnyPermission($permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    public function hasAllPermissions($permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    public function assignRole($role): void
    {
        if (is_string($role)) {
            $role = Role::where('tenant_id', $this->tenant_id)
                ->where('name', $role)
                ->first();
        }

        if ($role && !$this->hasRole($role)) {
            $this->roles()->attach($role->id, [
                'tenant_id' => $this->tenant_id,
            ]);
        }
    }

    public function removeRole($role): void
    {
        if (is_string($role)) {
            $role = Role::where('tenant_id', $this->tenant_id)
                ->where('name', $role)
                ->first();
        }

        if ($role) {
            $this->roles()->detach($role->id);
        }
    }

    public function syncRoles($roles): void
    {
        $roleIds = collect($roles)->map(function ($role) {
            if (is_string($role)) {
                return Role::where('tenant_id', $this->tenant_id)
                    ->where('name', $role)
                    ->first()
                    ?->id;
            }
            return $role->id;
        })->filter()->toArray();

        $pivotData = collect($roleIds)->mapWithKeys(function ($roleId) {
            return [$roleId => ['tenant_id' => $this->tenant_id]];
        });

        $this->roles()->sync($pivotData);
    }

    public function getPermissions()
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->flatMap(fn ($role) => $role->permissions)
            ->unique('id');
    }
}
