<?php

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Domain\Exceptions\TenantNotResolvedException;
use App\Modules\Tenancy\Domain\Models\Tenant;

/**
 * Mantém o contexto do tenant atual durante a requisição.
 *
 * O tenant é determinado no middleware e acessado globalmente
 * através deste singleton para garantir consistência.
 */
class TenantContext
{
    private ?string $tenantId = null;

    private ?Tenant $tenant = null;

    /**
     * Define o ID do tenant para a requisição atual.
     */
    public function set(string $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->tenant = null; // Invalidar cache do modelo
    }

    /**
     * Obtém o ID do tenant atual.
     *
     * @throws TenantNotResolvedException Se o tenant não foi resolvido
     */
    public function id(): string
    {
        if ($this->tenantId === null) {
            throw new TenantNotResolvedException('Tenant não foi resolvido nesta requisição');
        }

        return $this->tenantId;
    }

    /**
     * Obtém o modelo Tenant.
     *
     * @throws TenantNotResolvedException Se o tenant não foi resolvido
     */
    public function tenant(): Tenant
    {
        if ($this->tenant === null) {
            $this->tenant = Tenant::findOrFail($this->id());
        }

        return $this->tenant;
    }

    /**
     * Verifica se um tenant foi resolvido.
     */
    public function resolved(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Limpa o contexto (útil para testes).
     */
    public function clear(): void
    {
        $this->tenantId = null;
        $this->tenant = null;
    }

    /**
     * Executa uma callback com um tenant específico.
     *
     * Útil para jobs e operações assíncronas que precisam
     * executar em contexto de um tenant diferente.
     */
    public function withTenant(string $tenantId, callable $callback): mixed
    {
        $previousTenantId = $this->tenantId;

        try {
            $this->set($tenantId);

            return call_user_func($callback);
        } finally {
            if ($previousTenantId === null) {
                $this->clear();
            } else {
                $this->tenantId = $previousTenantId;
                $this->tenant = null;
            }
        }
    }
}
