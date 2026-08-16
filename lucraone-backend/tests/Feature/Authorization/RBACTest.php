<?php

namespace Tests\Feature\Authorization;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\TenancyTestCase;

class RBACTest extends TenancyTestCase
{
    use RefreshDatabase;

    /**
     * Teste 01: User pode ser atribuído a uma role.
     */
    public function test_user_can_be_assigned_role(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $role = Role::factory()
            ->admin()
            ->forTenant($this->tenantA->id)
            ->create();

        $user->assignRole($role);

        $this->assertTrue($user->hasRole($role));
        $this->assertTrue($user->hasRole('admin'));
    }

    /**
     * Teste 02: Role pode ser removida de um user.
     */
    public function test_user_can_have_role_removed(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $role = Role::factory()
            ->manager()
            ->forTenant($this->tenantA->id)
            ->create();

        $user->assignRole($role);
        $this->assertTrue($user->hasRole($role));

        $user->removeRole($role);
        $this->assertFalse($user->hasRole($role));
    }

    /**
     * Teste 03: User herda permissões da role.
     */
    public function test_user_inherits_permissions_from_role(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $role = Role::factory()
            ->forTenant($this->tenantA->id)
            ->create();

        $permission = Permission::factory()
            ->forTenant($this->tenantA->id)
            ->forAction('create', 'role')
            ->create();

        $role->grantPermission($permission);
        $user->assignRole($role);

        $this->assertTrue($user->hasPermission($permission));
        $this->assertTrue($user->hasPermission('create-role'));
    }

    /**
     * Teste 04: User sem role não tem permissões.
     */
    public function test_user_without_role_has_no_permissions(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $this->assertFalse($user->hasPermission('create-role'));
        $this->assertFalse($user->hasPermission('delete-role'));
    }

    /**
     * Teste 05: Role pode conceder permissão.
     */
    public function test_role_can_grant_permission(): void
    {
        $role = Role::factory()
            ->admin()
            ->forTenant($this->tenantA->id)
            ->create();

        $permission = Permission::factory()
            ->forTenant($this->tenantA->id)
            ->create();

        $role->grantPermission($permission);

        $this->assertTrue($role->hasPermission($permission));
    }

    /**
     * Teste 06: Role pode revogar permissão.
     */
    public function test_role_can_revoke_permission(): void
    {
        $role = Role::factory()
            ->forTenant($this->tenantA->id)
            ->create();

        $permission = Permission::factory()
            ->forTenant($this->tenantA->id)
            ->create();

        $role->grantPermission($permission);
        $this->assertTrue($role->hasPermission($permission));

        $role->revokePermission($permission);
        $this->assertFalse($role->hasPermission($permission));
    }

    /**
     * Teste 07: Tenant A roles não acessíveis por tenant B.
     */
    public function test_roles_isolated_by_tenant(): void
    {
        $roleA = Role::factory()
            ->admin()
            ->forTenant($this->tenantA->id)
            ->create();

        $userB = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantB->id)
            ->create();

        $userB->assignRole($roleA);

        $this->assertFalse($userB->hasRole($roleA));
    }

    /**
     * Teste 08: Permissions isoladas por tenant.
     */
    public function test_permissions_isolated_by_tenant(): void
    {
        $permissionA = Permission::factory()
            ->forTenant($this->tenantA->id)
            ->create();

        $userB = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantB->id)
            ->create();

        $roleB = Role::factory()
            ->forTenant($this->tenantB->id)
            ->create();

        $roleB->grantPermission($permissionA);
        $userB->assignRole($roleB);

        $this->assertFalse($userB->hasPermission($permissionA));
    }

    /**
     * Teste 09: User pode ter múltiplas roles.
     */
    public function test_user_can_have_multiple_roles(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $adminRole = Role::factory()
            ->admin()
            ->forTenant($this->tenantA->id)
            ->create();

        $managerRole = Role::factory()
            ->manager()
            ->forTenant($this->tenantA->id)
            ->create();

        $user->assignRole($adminRole);
        $user->assignRole($managerRole);

        $this->assertTrue($user->hasAllRoles([$adminRole, $managerRole]));
    }

    /**
     * Teste 10: hasAnyRole retorna true se user tem uma de várias roles.
     */
    public function test_user_has_any_role(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $adminRole = Role::factory()
            ->admin()
            ->forTenant($this->tenantA->id)
            ->create();

        $userRole = Role::factory()
            ->user()
            ->forTenant($this->tenantA->id)
            ->create();

        $user->assignRole($userRole);

        $this->assertTrue($user->hasAnyRole([$adminRole, $userRole]));
        $this->assertFalse($user->hasAnyRole([$adminRole]));
    }

    /**
     * Teste 11: syncRoles substitui todas as roles do user.
     */
    public function test_sync_roles_replaces_all_user_roles(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $adminRole = Role::factory()
            ->admin()
            ->forTenant($this->tenantA->id)
            ->create();

        $userRole = Role::factory()
            ->user()
            ->forTenant($this->tenantA->id)
            ->create();

        $user->assignRole($adminRole);
        $this->assertTrue($user->hasRole($adminRole));

        $user->syncRoles([$userRole]);
        $this->assertFalse($user->hasRole($adminRole));
        $this->assertTrue($user->hasRole($userRole));
    }

    /**
     * Teste 12: User pode buscar todas as suas permissões.
     */
    public function test_user_can_get_all_permissions(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $role = Role::factory()
            ->forTenant($this->tenantA->id)
            ->create();

        $perm1 = Permission::factory()
            ->forTenant($this->tenantA->id)
            ->create();
        $perm2 = Permission::factory()
            ->forTenant($this->tenantA->id)
            ->create();

        $role->grantPermission($perm1);
        $role->grantPermission($perm2);
        $user->assignRole($role);

        $permissions = $user->getPermissions();
        $this->assertCount(2, $permissions);
    }
}
