<?php

namespace Tests\Feature\Authorization;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\TenancyTestCase;

class AuthorizationIsolationTest extends TenancyTestCase
{
    use RefreshDatabase;

    /**
     * Teste 01: Roles de tenant A não visível para tenant B.
     */
    public function test_tenant_a_roles_not_visible_to_tenant_b(): void
    {
        $roleA = Role::factory()
            ->admin()
            ->forTenant($this->tenantA->id)
            ->create();

        $this->tenantContext->set($this->tenantB->id);
        $rolesInB = Role::where('tenant_id', $this->tenantB->id)->get();

        $this->assertFalse($rolesInB->contains($roleA));
    }

    /**
     * Teste 02: Permissions de tenant A não acessível para tenant B.
     */
    public function test_tenant_a_permissions_not_accessible_to_tenant_b(): void
    {
        $permissionA = Permission::factory()
            ->forTenant($this->tenantA->id)
            ->create();

        $roleB = Role::factory()
            ->forTenant($this->tenantB->id)
            ->create();

        $roleB->grantPermission($permissionA);

        $this->assertFalse($roleB->hasPermission($permissionA));
    }

    /**
     * Teste 03: User de tenant A não pode ter role de tenant B.
     */
    public function test_user_from_tenant_a_cannot_have_role_from_tenant_b(): void
    {
        $userA = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $roleB = Role::factory()
            ->forTenant($this->tenantB->id)
            ->create();

        $userA->assignRole($roleB);

        $this->assertFalse($userA->hasRole($roleB));
    }

    /**
     * Teste 04: Mesma role name pode existir em diferentes tenants.
     */
    public function test_same_role_name_can_exist_in_different_tenants(): void
    {
        $adminA = Role::factory()
            ->admin()
            ->forTenant($this->tenantA->id)
            ->create();

        $adminB = Role::factory()
            ->admin()
            ->forTenant($this->tenantB->id)
            ->create();

        $this->assertEquals('admin', $adminA->name);
        $this->assertEquals('admin', $adminB->name);
        $this->assertNotEquals($adminA->id, $adminB->id);
    }

    /**
     * Teste 05: Roles scoped automaticamente por tenant context.
     */
    public function test_roles_scoped_automatically_by_tenant(): void
    {
        $roleA = Role::factory()->forTenant($this->tenantA->id)->create();
        $roleB = Role::factory()->forTenant($this->tenantB->id)->create();

        $this->tenantContext->set($this->tenantA->id);
        $allRoles = Role::all();

        $this->assertTrue($allRoles->contains($roleA));
        $this->assertFalse($allRoles->contains($roleB));
    }
}
