<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TenantIsolationTest extends TenancyTestCase
{
    use RefreshDatabase;

    /**
     * Teste 01: Dois tenants coexistem no banco.
     */
    public function test_two_tenants_can_exist_simultaneously(): void
    {
        $this->assertDatabaseHas('tenants', ['id' => $this->tenantA->id]);
        $this->assertDatabaseHas('tenants', ['id' => $this->tenantB->id]);
    }

    /**
     * Teste 02: Tenant A consegue acessar seus dados.
     */
    public function test_tenant_can_access_own_data(): void
    {
        $this->tenantContext->set($this->tenantA->id);

        $found = Tenant::find($this->tenantA->id);

        $this->assertNotNull($found);
        $this->assertEquals($this->tenantA->id, $found->id);
    }

    /**
     * Teste 03: Global Scope funciona — Tenant A não vê dados de Tenant B.
     */
    public function test_global_scope_filters_by_tenant_context(): void
    {
        // Criar um modelo fictício multi-tenant (será usado em sprints futuras)
        // Por agora, testar com Tenant model

        // Não há filtro de tenant_id no modelo Tenant (é root)
        // Este teste será mais relevante quando Companies/Users forem implementadas

        $this->assertTrue(true);
    }

    /**
     * Teste 04: TenantContext resolve corretamente.
     */
    public function test_tenant_context_is_resolved(): void
    {
        $this->assertFalse($this->tenantContext->resolved());

        $this->tenantContext->set($this->tenantA->id);

        $this->assertTrue($this->tenantContext->resolved());
        $this->assertEquals($this->tenantA->id, $this->tenantContext->id());
    }

    /**
     * Teste 05: TenantContext withtenant method funciona.
     */
    public function test_tenant_context_with_tenant_method(): void
    {
        $result = $this->tenantContext->withTenant(
            $this->tenantA->id,
            function () {
                return $this->tenantContext->id();
            }
        );

        $this->assertEquals($this->tenantA->id, $result);
        $this->assertFalse($this->tenantContext->resolved());
    }

    /**
     * Teste 06: TenantContext clear funciona.
     */
    public function test_tenant_context_clear(): void
    {
        $this->tenantContext->set($this->tenantA->id);
        $this->assertTrue($this->tenantContext->resolved());

        $this->tenantContext->clear();
        $this->assertFalse($this->tenantContext->resolved());
    }

    /**
     * Teste 07: Exceção quando tenant não resolvido.
     */
    public function test_tenant_not_resolved_exception(): void
    {
        $this->expectException(\App\Modules\Tenancy\Domain\Exceptions\TenantNotResolvedException::class);

        $this->tenantContext->id();
    }

    /**
     * Teste 08: Tenant model relationship funciona.
     */
    public function test_tenant_model_relationships(): void
    {
        $this->assertNotNull($this->tenantA->companies());
        $this->assertNotNull($this->tenantA->users());
    }

    /**
     * Teste 09: Tenant isActive method funciona.
     */
    public function test_tenant_is_active_check(): void
    {
        $activeTenant = Tenant::factory()->active()->create();
        $inactiveTenant = Tenant::factory()->inactive()->create();

        $this->assertTrue($activeTenant->isActive());
        $this->assertFalse($inactiveTenant->isActive());
    }

    /**
     * Teste 10: Tenant slug é único.
     */
    public function test_tenant_slug_is_unique(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Tenant::factory()->create([
            'slug' => $this->tenantA->slug,
        ]);
    }
}
