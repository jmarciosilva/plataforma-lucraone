<?php

namespace App\Modules\Tenancy\Application;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Resolve qual tenant está sendo usado na requisição atual.
 *
 * Estratégia inicial: obtém o tenant do usuário autenticado.
 * Futuras estratégias podem incluir subdomain, header, etc.
 */
class TenantResolver
{
    public function __construct(
        private TenantContext $context
    ) {}

    /**
     * Resolve o tenant para a requisição.
     *
     * Retorna true se conseguiu resolver, false caso contrário.
     */
    public function resolve(Request $request): bool
    {
        // Estratégia 1: Obter do header X-Tenant-ID (útil para testes)
        if ($request->header('X-Tenant-ID')) {
            $this->context->set($request->header('X-Tenant-ID'));

            return true;
        }

        // Estratégia 2: Obter do usuário autenticado
        if (Auth::check() && Auth::user()?->tenant_id) {
            $this->context->set(Auth::user()->tenant_id);

            return true;
        }

        // Futuras estratégias:
        // - Subdomain
        // - Domain

        return false;
    }
}
