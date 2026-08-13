<?php

namespace Tests\Feature\Tenancy;

use Tests\TestCase;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Application\TenantContext;

/**
 * Base test case para testes de tenancy.
 *
 * Fornece helpers para criar múltiplos tenants e validar isolamento.
 */
abstract class TenancyTestCase extends TestCase
{
    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected TenantContext $tenantContext;

    public function setUp(): void
    {
        parent::setUp();

        $this->tenantContext = app(TenantContext::class);

        // Criar dois tenants para testes de isolamento
        $this->tenantA = Tenant::factory()->active()->create();
        $this->tenantB = Tenant::factory()->active()->create();
    }

    public function tearDown(): void
    {
        // Limpar contexto após cada teste
        $this->tenantContext->clear();
        parent::tearDown();
    }

    /**
     * Executar teste dentro do contexto de um tenant.
     */
    protected function withTenant(Tenant $tenant, callable $callback): mixed
    {
        return $this->tenantContext->withTenant($tenant->id, $callback);
    }

    /**
     * Assert que um recurso pertence ao tenant correto.
     */
    protected function assertBelongsToTenant($model, Tenant $tenant): void
    {
        $this->assertEquals(
            $tenant->id,
            $model->tenant_id,
            "Modelo não pertence ao tenant {$tenant->id}"
        );
    }

    /**
     * Assert que um modelo não é acessível para um tenant.
     */
    protected function assertNotAccessibleToTenant($modelClass, $modelId, Tenant $tenant): void
    {
        $this->tenantContext->set($tenant->id);

        $found = $modelClass::where('id', $modelId)->first();

        $this->assertNull(
            $found,
            "Modelo deveria não ser acessível para tenant {$tenant->id}"
        );
    }
}
