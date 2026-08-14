<?php

namespace App\Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Modules\Tenancy\Application\TenantResolver;
use App\Modules\Tenancy\Domain\Exceptions\TenantNotResolvedException;

class ResolveTenantMiddleware
{
    public function __construct(
        private TenantResolver $resolver
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * Resolve qual tenant está sendo usado antes de processar a requisição.
     * Se não conseguir resolver e a rota exigir autenticação, retorna erro.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Rotas públicas (login, health check, etc) não precisam de tenant
        if ($this->isPublicRoute($request)) {
            return $next($request);
        }

        // Rotas autenticadas precisam de tenant resolvido
        if (!$this->resolver->resolve($request)) {
            throw new TenantNotResolvedException(
                'Não foi possível resolver o tenant para esta requisição'
            );
        }

        return $next($request);
    }

    /**
     * Verifica se a rota é pública (não requer tenant).
     */
    private function isPublicRoute(Request $request): bool
    {
        // Health checks (Laravel 11)
        if ($request->is('up')) {
            return true;
        }

        // Autenticação (ambos os padrões de versão)
        if ($request->is(
            'api/auth/login',
            'api/auth/logout',
            'api/v1/auth/login',
            'api/v1/auth/logout',
            'api/v1/auth/forgot-password',
            'api/v1/auth/reset-password'
        )) {
            return true;
        }

        // Rotas de teste (Laravel Dusk / teste local)
        if ($request->is('tests/*') || $request->getMethod() === 'GET' && $request->path() === '/') {
            return true;
        }

        return false;
    }
}
