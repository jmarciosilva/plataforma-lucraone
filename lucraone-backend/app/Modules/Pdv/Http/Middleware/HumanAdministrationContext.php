<?php

namespace App\Modules\Pdv\Http\Middleware;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\TokenAbility;
use App\Modules\Pdv\Http\Responses\PdvErrorResponse;
use App\Modules\Tenancy\Application\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Porta HUMANA das rotas administrativas do PDV.
 *
 * Exige, nesta ordem: sujeito do tipo User, PAT real (não sessão de primeira
 * parte), ability `business:write` e estabelecimento resolvido pelo mecanismo
 * humano. Aqui `X-Tenant-ID` é seleção humana VALIDADA pelo TenantResolver —
 * que confere conta ativa e vínculo ativo —, nunca prova de autorização; o
 * oposto das rotas de máquina, onde o cabeçalho é recusado porque o Terminal
 * já fixa o contexto.
 *
 * Nenhuma credencial de máquina entra por aqui, e nenhum token humano ganha
 * alcance de máquina: são dois espaços de abilities separados.
 *
 * A recusa é genérica: um 403 igual para sujeito errado, token sem alcance e
 * estabelecimento não resolvido. Distinguir diria a quem tem só um token velho
 * qual metade do problema resolver.
 */
class HumanAdministrationContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if (! $user instanceof User
            || ! $token instanceof PersonalAccessToken
            || ! $token->can(TokenAbility::ESCRITA)
            || ! app(TenantResolver::class)->resolve($request)) {
            return PdvErrorResponse::make($request, PdvErrorResponse::CODE_FORBIDDEN, 'Operação administrativa não autorizada.', 403);
        }

        return $next($request);
    }
}
