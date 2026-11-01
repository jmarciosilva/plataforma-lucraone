<?php

namespace App\Modules\Tenancy\Application;

use App\Modules\Branches\Domain\Models\Branch;
use Illuminate\Http\Request;

/**
 * Determina qual filial está ativa para a requisição.
 *
 * Análogo ao TenantResolver. A resolução é não-bloqueante: quando nenhuma
 * filial pode ser resolvida o sistema opera em visão consolidada de tenant,
 * preservando o comportamento atual do supermercado (Decisão B-2).
 *
 * Estratégias em ordem:
 *   1. Sessão 'filial_ativa' — validada contra tenant atual e status ACTIVE.
 *   2. Filial única ativa do tenant — auto-seleciona.
 *   3. Sem resolução — retorna false, contexto limpo (visão consolidada).
 *
 * Segurança: o contexto de filial nunca é lido da URL nem do body. A chave de
 * sessão é assinada pelo Laravel, e o ID é sempre validado contra o tenant
 * ativo antes de ser aceito.
 */
class BranchResolver
{
    public const SESSAO_FILIAL = 'filial_ativa';

    public function __construct(
        private BranchContext $context,
        private TenantContext $tenantContext,
    ) {}

    /**
     * Tenta resolver a filial ativa. Retorna true quando resolvida.
     *
     * Falso não é erro: é visão consolidada de tenant. Quem precisa de uma
     * filial específica redireciona para a seleção se !resolved().
     */
    public function resolve(Request $request): bool
    {
        if (! $this->tenantContext->resolved()) {
            return $this->recusar();
        }

        $tenantId = $this->tenantContext->id();

        // Estratégia 1: filial na sessão
        if ($request->hasSession()) {
            $branchId = $request->session()->get(self::SESSAO_FILIAL);

            if ($branchId && $this->validar($branchId, $tenantId)) {
                $this->context->set($branchId);

                return true;
            }

            // Sessão inválida (filial desativada, acesso revogado, troca de
            // tenant) — limpa silenciosamente e tenta a estratégia 2.
            if ($branchId) {
                $request->session()->forget(self::SESSAO_FILIAL);
            }
        }

        // Estratégia 2: filial única do tenant — auto-resolve sem interação
        $unica = Branch::where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->limit(2)
            ->get();

        if ($unica->count() === 1) {
            $this->context->set($unica->first()->id);

            return true;
        }

        return $this->recusar();
    }

    /**
     * Grava a filial escolhida na sessão, após validação.
     *
     * Retorna false se o ID não pertencer ao tenant atual ou a filial não
     * estiver ativa.
     */
    public function definir(Request $request, string $branchId): bool
    {
        if (! $this->tenantContext->resolved()) {
            return false;
        }

        if (! $this->validar($branchId, $this->tenantContext->id())) {
            return false;
        }

        $request->session()->put(self::SESSAO_FILIAL, $branchId);
        $this->context->set($branchId);

        return true;
    }

    /**
     * Remove a filial da sessão e limpa o contexto.
     * Chamado na troca de tenant para evitar contaminação cruzada.
     */
    public function invalidar(Request $request): void
    {
        $request->session()->forget(self::SESSAO_FILIAL);
        $this->context->clear();
    }

    // -------------------------------------------------------------------------

    private function validar(string $branchId, string $tenantId): bool
    {
        return Branch::where('id', $branchId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->exists();
    }

    private function recusar(): bool
    {
        $this->context->clear();

        return false;
    }
}
