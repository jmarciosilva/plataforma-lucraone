<?php

namespace App\Modules\Tenancy\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Modules\Tenancy\Application\TenantContext;

/**
 * Global Scope que filtra automaticamente modelos pelo tenant atual.
 *
 * Todos os modelos multi-tenant usam este scope para garantir que
 * queries retornem apenas dados do tenant autenticado.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Obter contexto de tenant
        $tenantContext = app(TenantContext::class);

        // Se tenant foi resolvido, aplicar filtro
        if ($tenantContext->resolved()) {
            $builder->where($model->getTable() . '.tenant_id', '=', $tenantContext->id());
        }
    }
}
