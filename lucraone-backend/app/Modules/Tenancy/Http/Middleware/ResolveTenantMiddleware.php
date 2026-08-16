<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Application\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fixa o estabelecimento da requisição.
 *
 * Aplicado explicitamente nas rotas que precisam dele, sempre DEPOIS da
 * autenticação — o vínculo do usuário é o que autoriza o acesso ao
 * estabelecimento pedido.
 *
 * Registrado como alias 'tenant'. Uso: ['auth:sanctum', 'tenant'].
 *
 * Nunca registrar como middleware global: rodando antes da autenticação, não
 * há usuário para validar, e um X-Tenant-ID apontando para outro
 * estabelecimento seria aceito sem checagem.
 */
class ResolveTenantMiddleware
{
    public function __construct(
        private TenantResolver $resolver
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->resolver->resolve($request)) {
            return $next($request);
        }

        // Não resolveu: ou o usuário não tem vínculo ativo com o
        // estabelecimento pedido, ou tem vários e não indicou qual.
        return response()->json([
            'message' => 'Estabelecimento não identificado ou sem vínculo ativo',
        ], 403);
    }
}
