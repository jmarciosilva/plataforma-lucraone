<?php

namespace Tests\Unit\Tenancy;

use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Exceptions\TenantNotResolvedException;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

class TenantContextTest extends TestCase
{
    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = new TenantContext;
    }

    /**
     * Teste: Context inicia sem tenant.
     */
    public function test_context_starts_without_tenant(): void
    {
        $this->assertFalse($this->context->resolved());
    }

    /**
     * Teste: Pode definir tenant ID.
     */
    public function test_can_set_tenant_id(): void
    {
        $tenantId = (string) Str::ulid();

        $this->context->set($tenantId);

        $this->assertTrue($this->context->resolved());
        $this->assertEquals($tenantId, $this->context->id());
    }

    /**
     * Teste: Lança exceção quando tenant não resolvido.
     */
    public function test_throws_exception_when_tenant_not_resolved(): void
    {
        $this->expectException(TenantNotResolvedException::class);

        $this->context->id();
    }

    /**
     * Teste: withTenant executa callback.
     */
    public function test_with_tenant_executes_callback(): void
    {
        $tenantId = (string) Str::ulid();
        $executed = false;

        $this->context->withTenant($tenantId, function () use (&$executed) {
            $executed = true;
        });

        $this->assertTrue($executed);
    }

    /**
     * Teste: withTenant retorna callback result.
     */
    public function test_with_tenant_returns_callback_result(): void
    {
        $tenantId = (string) Str::ulid();
        $result = 'test-result';

        $returned = $this->context->withTenant($tenantId, function () use ($result) {
            return $result;
        });

        $this->assertEquals($result, $returned);
    }

    /**
     * Teste: withTenant restaura context anterior.
     */
    public function test_with_tenant_restores_previous_context(): void
    {
        $tenantIdA = (string) Str::ulid();
        $tenantIdB = (string) Str::ulid();

        $this->context->set($tenantIdA);
        $actualTenantIdDuringCallback = null;

        $this->context->withTenant($tenantIdB, function () use (&$actualTenantIdDuringCallback) {
            // No callback, tenant deve ser B
            $actualTenantIdDuringCallback = $this->context->id();
        });

        // Verifica que B foi usado durante callback
        $this->assertEquals($tenantIdB, $actualTenantIdDuringCallback);

        // Após callback, volta para A
        $this->assertEquals($tenantIdA, $this->context->id());
    }

    /**
     * Teste: clear limpa o context.
     */
    public function test_clear_clears_context(): void
    {
        $tenantId = (string) Str::ulid();
        $this->context->set($tenantId);

        $this->assertTrue($this->context->resolved());

        $this->context->clear();

        $this->assertFalse($this->context->resolved());
    }
}
