<?php

namespace Tests\Feature\Identity;

use Tests\Feature\Tenancy\TenancyTestCase;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserIsolationTest extends TenancyTestCase
{
    use RefreshDatabase;

    /**
     * Teste 01: Usuários de diferentes tenants coexistem.
     */
    public function test_users_from_different_tenants_coexist(): void
    {
        $userA = User::factory()->forCurrentTenant($this->tenantA->id)->create();
        $userB = User::factory()->forCurrentTenant($this->tenantB->id)->create();

        $this->assertDatabaseHas('users', ['id' => $userA->id, 'tenant_id' => $this->tenantA->id]);
        $this->assertDatabaseHas('users', ['id' => $userB->id, 'tenant_id' => $this->tenantB->id]);
    }

    /**
     * Teste 02: Global scope filtra por tenant.
     */
    public function test_global_scope_filters_by_tenant(): void
    {
        User::factory()->forCurrentTenant($this->tenantA->id)->create();
        User::factory()->forCurrentTenant($this->tenantB->id)->create();

        $this->tenantContext->set($this->tenantA->id);
        $users = User::all();

        $this->assertCount(1, $users);
        $this->assertEquals($this->tenantA->id, $users->first()->tenant_id);
    }

    /**
     * Teste 03: Tenant A não consegue acessar usuários de Tenant B (IDOR).
     */
    public function test_tenant_a_cannot_access_tenant_b_users(): void
    {
        $userB = User::factory()->forCurrentTenant($this->tenantB->id)->create();

        $this->tenantContext->set($this->tenantA->id);
        $foundUser = User::find($userB->id);

        $this->assertNull($foundUser);
    }

    /**
     * Teste 04: Tenant A consegue acessar apenas seus usuários.
     */
    public function test_tenant_a_can_only_access_own_users(): void
    {
        $userA1 = User::factory()->forCurrentTenant($this->tenantA->id)->create();
        $userA2 = User::factory()->forCurrentTenant($this->tenantA->id)->create();
        User::factory()->forCurrentTenant($this->tenantB->id)->create();

        $this->tenantContext->set($this->tenantA->id);
        $users = User::all();

        $this->assertCount(2, $users);
        $this->assertTrue($users->contains('id', $userA1->id));
        $this->assertTrue($users->contains('id', $userA2->id));
    }

    /**
     * Teste 05: Trocar contexto de tenant funciona corretamente.
     */
    public function test_switching_tenant_context_works(): void
    {
        $userA = User::factory()->forCurrentTenant($this->tenantA->id)->create();
        $userB = User::factory()->forCurrentTenant($this->tenantB->id)->create();

        $this->tenantContext->set($this->tenantA->id);
        $this->assertCount(1, User::all());

        $this->tenantContext->set($this->tenantB->id);
        $this->assertCount(1, User::all());
    }

    /**
     * Teste 06: Email é único por tenant (não globalmente).
     */
    public function test_email_is_unique_per_tenant(): void
    {
        $email = 'user@example.com';

        User::factory()
            ->forCurrentTenant($this->tenantA->id)
            ->create(['email' => $email]);

        User::factory()
            ->forCurrentTenant($this->tenantB->id)
            ->create(['email' => $email]);

        $this->assertDatabaseCount('users', 2);
    }

    /**
     * Teste 07: Status ACTIVE/INVITED/INACTIVE funciona.
     */
    public function test_user_status_states_work(): void
    {
        $active = User::factory()->active()->create();
        $invited = User::factory()->invited()->create();
        $inactive = User::factory()->inactive()->create();

        $this->assertTrue($active->isActive());
        $this->assertTrue($invited->isInvited());
        $this->assertFalse($inactive->isActive());
    }

    /**
     * Teste 08: Usuário ativo tem email verificado.
     */
    public function test_active_user_has_verified_email(): void
    {
        $active = User::factory()->active()->create();

        $this->assertNotNull($active->email_verified_at);
    }

    /**
     * Teste 09: Usuário convidado não tem email verificado.
     */
    public function test_invited_user_has_unverified_email(): void
    {
        $invited = User::factory()->invited()->create();

        $this->assertNull($invited->email_verified_at);
    }

    /**
     * Teste 10: Password é sempre hashed (nunca em plain text).
     */
    public function test_password_is_always_hashed(): void
    {
        $user = User::factory()->create();

        $this->assertNotEquals('password', $user->password);
        $this->assertTrue(password_verify('password', $user->password));
    }
}
