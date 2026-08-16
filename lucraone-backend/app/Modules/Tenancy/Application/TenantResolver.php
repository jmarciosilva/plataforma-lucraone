<?php

namespace App\Modules\Tenancy\Application;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Determina em qual estabelecimento a requisição está operando.
 *
 * Desde o F1.8 o usuário pode estar associado a vários estabelecimentos, então
 * não basta ler um campo do usuário: é preciso saber qual ele escolheu, e
 * confirmar que o vínculo permite o acesso.
 */
class TenantResolver
{
    /**
     * Chave de sessão do estabelecimento em uso.
     *
     * Vive aqui, e não no controller, porque é este resolver quem a lê a cada
     * requisição — quem escreve apenas segue o contrato.
     */
    public const SESSAO_TENANT = 'tenant_ativo';

    public function __construct(
        private TenantContext $context
    ) {}

    /**
     * Retorna true se conseguiu resolver o estabelecimento.
     */
    public function resolve(Request $request): bool
    {
        $usuario = Auth::user();

        // Estratégia 1: header explícito (API e testes)
        if ($tenantId = $request->header('X-Tenant-ID')) {
            // Havendo usuário autenticado, o vínculo precisa autorizar
            if ($usuario && ! $usuario->canAccessTenant($tenantId)) {
                return false;
            }

            $this->context->set($tenantId);

            return true;
        }

        // Estratégia 2: escolha guardada na sessão (painel web)
        if ($usuario && $request->hasSession()) {
            $tenantId = $request->session()->get(self::SESSAO_TENANT);

            if ($tenantId && $usuario->canAccessTenant($tenantId)) {
                $this->context->set($tenantId);

                return true;
            }
        }

        // Estratégia 3: vínculo único — não há o que escolher
        if ($usuario) {
            $estabelecimentos = $usuario->estabelecimentosDisponiveis();

            if ($estabelecimentos->count() === 1) {
                $this->context->set($estabelecimentos->first()->id);

                return true;
            }
        }

        // Futuras estratégias: subdomínio, domínio próprio

        return false;
    }
}
