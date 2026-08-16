<?php

namespace App\Modules\Tenancy\Domain\Models;

use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Infrastructure\Persistence\TenantScope;

/**
 * Trait para modelos que pertencem a um tenant.
 *
 * Adiciona automaticamente o TenantScope e força a coluna tenant_id
 * em todas as operações de banco de dados.
 */
trait HasTenant
{
    /**
     * Registra o global scope ao fazer boot do modelo.
     */
    public static function bootHasTenant(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * Obter o tenant_id deste modelo.
     */
    public function getTenantId(): string
    {
        return $this->getAttribute('tenant_id');
    }

    /**
     * Definir o tenant_id deste modelo.
     */
    public function setTenantId(string $tenantId): void
    {
        $this->setAttribute('tenant_id', $tenantId);
    }

    /**
     * Criar modelo associado ao tenant atual.
     *
     * Preenche automaticamente tenant_id com o contexto atual.
     */
    public static function forCurrentTenant(array $attributes = []): static
    {
        $tenantContext = app(TenantContext::class);
        $attributes['tenant_id'] = $tenantContext->id();

        return new static($attributes);
    }
}
