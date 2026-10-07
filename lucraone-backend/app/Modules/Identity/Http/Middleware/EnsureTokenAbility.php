<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Domain\TokenAbility;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confere se o token da requisição tem a ability da operação pedida.
 *
 * Um único ponto para as 35 rotas de negócio: a ability necessária sai do
 * método HTTP, então não é preciso repetir a checagem em rota nem em
 * controller — e não se duplica o que as Policies do SEC-01 já decidem.
 *
 * Registrado como alias `token.ability`. Uso:
 * `['auth:sanctum', 'token.ability', 'tenant']` — nessa ordem. Precisa rodar
 * DEPOIS da autenticação, senão não há token para conferir; e antes de
 * `tenant` apenas por clareza, já que as duas checagens são independentes.
 */
class EnsureTokenAbility
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // Sessão de primeira parte (cookie) não carrega token de acesso. O
        // painel web é autorizado pelas Policies, não por ability.
        if (! $usuario || ! $usuario->currentAccessToken()) {
            return $next($request);
        }

        $necessaria = $request->isMethodSafe()
            ? TokenAbility::LEITURA
            : TokenAbility::ESCRITA;

        if (! $usuario->tokenCan($necessaria)) {
            return response()->json([
                'message' => 'Este token não tem permissão para esta operação',
            ], 403);
        }

        return $next($request);
    }
}
