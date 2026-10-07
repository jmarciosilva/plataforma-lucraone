<?php

namespace App\Modules\Tenancy\Application;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Http\Request;

/**
 * Determina em qual estabelecimento a requisição está operando.
 *
 * Desde o F1.8 o usuário pode estar associado a vários estabelecimentos, então
 * não basta ler um campo do usuário: é preciso saber qual ele escolheu, e
 * confirmar que o vínculo permite o acesso.
 *
 * `X-Tenant-ID` é um **pedido** de contexto, nunca prova de autorização. Quem
 * autoriza é o vínculo de uma identidade autenticada; o header apenas diz qual
 * dos vínculos dessa identidade usar. Sem identidade não há pedido a atender,
 * e o resolver recusa — independentemente de quais middlewares rodaram antes
 * (SEC-03).
 *
 * O que este resolver NÃO faz: autorizar operação de negócio. Isso é das
 * Policies (SEC-01). Aqui só se decide em qual estabelecimento a requisição
 * acontece.
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
        // A identidade vem da requisição, não do guard padrão: é o sujeito que
        // a autenticação desta requisição estabeleceu. É também como o resto
        // do projeto lê o usuário.
        $usuario = $request->user();

        // Fail-closed. Antes do SEC-03 esta checagem era condicional — só
        // valia "havendo usuário" —, então uma requisição sem identidade
        // alguma fixava o contexto a partir do header. Não era explorável
        // porque toda rota de negócio põe `auth:sanctum` antes de `tenant`,
        // mas a proteção morava na ordem dos middlewares, não aqui.
        //
        // O teste é por tipo, e não só por presença: estabelecimento é
        // derivado de vínculo de pessoa, o que só existe para `User`. Um
        // sujeito não-humano futuro — terminal de PDV, integração — não é
        // `User`, cai neste ponto e é recusado. Quando esse sujeito existir
        // precisará de uma estratégia própria e explícita, em vez de pegar
        // carona no `X-Tenant-ID`.
        if (! $usuario instanceof User) {
            return $this->recusar();
        }

        // Estratégia 1: header explícito (API e testes)
        if ($tenantId = $request->header('X-Tenant-ID')) {
            // O vínculo é que autoriza. `canAccessTenant` exige conta ativa e
            // vínculo ativo com este estabelecimento, então id inexistente ou
            // malformado simplesmente não casa.
            if (! $usuario->canAccessTenant($tenantId)) {
                return $this->recusar();
            }

            $this->context->set($tenantId);

            return true;
        }

        // Estratégia 2: escolha guardada na sessão (painel web)
        if ($request->hasSession()) {
            $tenantId = $request->session()->get(self::SESSAO_TENANT);

            if ($tenantId && $usuario->canAccessTenant($tenantId)) {
                $this->context->set($tenantId);

                return true;
            }

            // Sessão inválida não aborta: pode ser um acesso revogado enquanto
            // a pessoa navegava, e o vínculo único abaixo ainda resolve.
        }

        // Estratégia 3: vínculo único — não há o que escolher
        $estabelecimentos = $usuario->estabelecimentosDisponiveis();

        if ($estabelecimentos->count() === 1) {
            $this->context->set($estabelecimentos->first()->id);

            return true;
        }

        // Futuras estratégias: subdomínio, domínio próprio

        return $this->recusar();
    }

    /**
     * Recusa a resolução sem deixar contexto pela metade.
     *
     * O contexto é o que o TenantScope usa para filtrar. Uma falha que
     * deixasse em pé o estabelecimento de uma resolução anterior faria as
     * consultas seguintes rodarem no estabelecimento errado — por isso a
     * recusa limpa, em vez de apenas devolver false.
     */
    private function recusar(): bool
    {
        $this->context->clear();

        return false;
    }
}
