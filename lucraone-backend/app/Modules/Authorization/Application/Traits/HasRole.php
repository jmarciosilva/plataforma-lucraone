<?php

namespace App\Modules\Authorization\Application\Traits;

use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Tenancy\Application\TenantContext;

/**
 * Papéis e permissões são sempre relativos a um estabelecimento.
 *
 * A mesma pessoa pode ser Admin numa loja e Viewer em outra, então o
 * estabelecimento de referência vem do TenantContext da requisição — não do
 * usuário, que não pertence mais a um único tenant.
 *
 * Os métodos aceitam $tenantId explícito para uso fora de uma requisição
 * (jobs, comandos, testes).
 */
trait HasRole
{
    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'user_role',
            'user_id',
            'role_id'
        )
            ->withoutGlobalScopes()
            ->withTimestamps();
    }

    /**
     * Estabelecimento de referência, em ordem de precedência:
     *
     *   1. o informado explicitamente
     *   2. o ativo na requisição
     *   3. o único vínculo ativo da pessoa — sem ambiguidade a resolver
     *
     * O passo 3 cobre jobs, comandos e testes, onde não há requisição.
     */
    protected function tenantDeReferencia(?string $tenantId = null): ?string
    {
        if ($tenantId !== null) {
            return $tenantId;
        }

        $contexto = app(TenantContext::class);

        if ($contexto->resolved()) {
            return $contexto->id();
        }

        $vinculos = $this->memberships()
            ->where('status', 'ACTIVE')
            ->pluck('tenant_id');

        return $vinculos->count() === 1 ? $vinculos->first() : null;
    }

    public function rolesForTenant(?string $tenantId = null)
    {
        return $this->roles()
            ->wherePivot('tenant_id', $this->tenantDeReferencia($tenantId));
    }

    public function hasRole($role, ?string $tenantId = null): bool
    {
        if (is_string($role)) {
            return $this->rolesForTenant($tenantId)->where('roles.name', $role)->exists();
        }

        return $this->rolesForTenant($tenantId)->where('roles.id', $role->id)->exists();
    }

    public function hasAnyRole($roles, ?string $tenantId = null): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role, $tenantId)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllRoles($roles, ?string $tenantId = null): bool
    {
        foreach ($roles as $role) {
            if (! $this->hasRole($role, $tenantId)) {
                return false;
            }
        }

        return true;
    }

    public function hasPermission($permission, ?string $tenantId = null): bool
    {
        $permissoes = $this->getPermissions($tenantId);

        if (is_string($permission)) {
            return $permissoes->firstWhere('name', $permission) !== null;
        }

        return $permissoes->firstWhere('id', $permission->id) !== null;
    }

    public function hasAnyPermission($permissions, ?string $tenantId = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission, $tenantId)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions($permissions, ?string $tenantId = null): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission, $tenantId)) {
                return false;
            }
        }

        return true;
    }

    public function assignRole($role, ?string $tenantId = null): void
    {
        $tenant = $this->tenantDeReferencia($tenantId);

        if ($tenant === null) {
            return;
        }

        if (is_string($role)) {
            $role = Role::withoutGlobalScopes()
                ->where('tenant_id', $tenant)
                ->where('name', $role)
                ->first();
        }

        // O papel precisa pertencer ao mesmo estabelecimento do vínculo
        if ($role && $role->tenant_id === $tenant && ! $this->hasRole($role, $tenant)) {
            $this->roles()->attach($role->id, ['tenant_id' => $tenant]);
        }
    }

    public function removeRole($role, ?string $tenantId = null): void
    {
        $tenant = $this->tenantDeReferencia($tenantId);

        if ($tenant === null) {
            return;
        }

        if (is_string($role)) {
            $role = Role::withoutGlobalScopes()
                ->where('tenant_id', $tenant)
                ->where('name', $role)
                ->first();
        }

        if ($role) {
            $this->roles()
                ->wherePivot('tenant_id', $tenant)
                ->detach($role->id);
        }
    }

    /**
     * Substitui os papéis da pessoa NESTE estabelecimento, preservando os
     * papéis que ela tenha em outros.
     */
    public function syncRoles($roles, ?string $tenantId = null): void
    {
        $tenant = $this->tenantDeReferencia($tenantId);

        if ($tenant === null) {
            return;
        }

        $roleIds = collect($roles)->map(function ($role) use ($tenant) {
            if (is_string($role)) {
                return Role::withoutGlobalScopes()
                    ->where('tenant_id', $tenant)
                    ->where(fn ($query) => $query
                        ->where('id', $role)
                        ->orWhere('name', $role))
                    ->first()
                    ?->id;
            }

            return $role->id;
        })->filter()->values();

        // Remove só os vínculos deste estabelecimento
        $this->roles()->wherePivot('tenant_id', $tenant)->detach();

        foreach ($roleIds as $roleId) {
            $this->roles()->attach($roleId, ['tenant_id' => $tenant]);
        }
    }

    public function getPermissions(?string $tenantId = null)
    {
        return $this->rolesForTenant($tenantId)
            ->with('permissions')
            ->get()
            ->flatMap(fn ($role) => $role->permissions)
            ->unique('id')
            ->values();
    }
}
