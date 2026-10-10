<?php

namespace App\Modules\Terminals\Http\Middleware;

use App\Modules\Pdv\Http\Responses\PdvErrorResponse;
use App\Modules\Terminals\Domain\Models\Terminal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confere a ability da credencial de máquina.
 *
 * Mecanismo próprio, e não o EnsureTokenAbility humano, porque aquele deriva a
 * ability do método HTTP (GET → business:read, resto → business:write). Esse
 * mapeamento é a regra da sessão humana; aplicá-lo a máquina faria um GET de
 * PDV exigir `business:read` e devolveria a máquina para o espaço de abilities
 * das pessoas.
 *
 * Aqui a ability é declarada na rota, explicitamente, uma por operação:
 * `machine.ability:pdv:terminal:read`. O resolvedor de middleware do Laravel
 * separa nome e parâmetro no PRIMEIRO ':', então os dois-pontos internos da
 * ability chegam intactos.
 *
 * Registrado como alias 'machine.ability'. Roda DEPOIS de 'terminal.context',
 * que é quem garante o tipo do sujeito — este middleware confere alcance, e
 * alcance não substitui tipo.
 */
class EnsureMachineTokenAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $sujeito = $request->user();

        if (! $sujeito instanceof Terminal) {
            return PdvErrorResponse::make(
                $request,
                PdvErrorResponse::CODE_FORBIDDEN,
                'Esta operação exige uma credencial de Terminal.',
                403,
            );
        }

        // Fail-closed em vez de "deixa passar quem não tem token": o caminho de
        // máquina não tem equivalente da sessão por cookie, então ausência de
        // credencial é recusa. Não há teste separado de nulidade porque o
        // `tokenCan()` do Sanctum já é falso quando a requisição não trouxe
        // token — dois testes para a mesma condição só criariam um ramo morto.
        if (! $sujeito->tokenCan($ability)) {
            return PdvErrorResponse::make(
                $request,
                PdvErrorResponse::CODE_FORBIDDEN,
                'Esta credencial não tem permissão para esta operação.',
                403,
            );
        }

        return $next($request);
    }
}
