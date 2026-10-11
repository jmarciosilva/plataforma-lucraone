<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Authorization\Application\TenantAuthority;
use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Domain\Exceptions\OperatorSessionFailed;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\OperatorPermission;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\TerminalStructure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Concessão e revogação da atribuição de operadores a filiais.
 *
 * Duas autoridades são exigidas juntas, e nenhuma substitui a outra:
 *
 *   - sobre a FILIAL, a mesma do TerminalPolicy: `BranchPolicy::update`, que
 *     pede `manage-branches` no estabelecimento resolvido e recusa filial de
 *     outro. É o que impede administrar um caixa de uma loja que não é sua;
 *   - sobre a PESSOA, a contenção do SEC-04 · E2: administrar alguém exige
 *     dominar a autoridade dos papéis dele. É o que impede um gerente de
 *     conceder acesso de caixa a um administrador e, por essa via, mexer no
 *     acesso de quem está acima dele.
 *
 * Nenhum papel dá passe livre: Platform Admin, nome de papel e permissão de
 * estabelecimento isolada não dispensam as duas verificações acima.
 *
 * Toda transição é transacional e serializa na linha da atribuição — concessão
 * e revogação simultâneas da mesma tripla não podem produzir dois resultados.
 */
class OperatorBranchAccess
{
    public function __construct(
        private TenantAuthority $autoridade,
        private OperatorSessionLifecycle $sessoes,
        private OperatorSessionAudit $auditoria,
    ) {}

    /**
     * @throws OperatorSessionFailed
     */
    public function grant(User $ator, User $operador, Branch $filial): PdvOperatorBranchAssignment
    {
        return DB::transaction(function () use ($ator, $operador, $filial) {
            $this->exigirAutoridade($ator, $operador, $filial);

            // Reativa a linha existente em vez de criar outra: o índice único
            // da tripla é o que mantém uma só verdade sobre "esta pessoa opera
            // esta filial?".
            $atribuicao = PdvOperatorBranchAssignment::travarPara($filial->tenant_id, $operador->id, $filial->id);

            if ($atribuicao !== null && $atribuicao->isActive()) {
                return $atribuicao;
            }

            if ($atribuicao === null) {
                $atribuicao = new PdvOperatorBranchAssignment([
                    'tenant_id' => $filial->tenant_id,
                    'user_id' => $operador->id,
                    'branch_id' => $filial->id,
                ]);
            }

            $atribuicao->forceFill([
                'status' => PdvOperatorBranchAssignment::STATUS_ACTIVE,
                'granted_by' => $ator->id,
                'revoked_at' => null,
            ])->save();

            $this->auditoria->atribuicao(OperatorSessionAudit::BRANCH_ACCESS_GRANTED, $atribuicao, $ator);

            return $atribuicao;
        }, 3);
    }

    /**
     * Revoga e derruba as sessões em andamento daquela pessoa naquela filial.
     *
     * Idempotente: revogar o que já está revogado não registra duas vezes nem
     * falha. Revogar o que nunca existiu é 403, como consultar o que não se
     * pode ver — não há endpoint que confirme a ausência de um vínculo.
     *
     * @throws OperatorSessionFailed
     */
    public function revoke(User $ator, User $operador, Branch $filial): PdvOperatorBranchAssignment
    {
        return DB::transaction(function () use ($ator, $operador, $filial) {
            $this->exigirAutoridade($ator, $operador, $filial, exigirVinculoAtivo: false);

            $atribuicao = PdvOperatorBranchAssignment::travarPara($filial->tenant_id, $operador->id, $filial->id);

            if ($atribuicao === null) {
                throw OperatorSessionFailed::proibido();
            }

            if (! $atribuicao->isActive()) {
                return $atribuicao;
            }

            $atribuicao->forceFill([
                'status' => PdvOperatorBranchAssignment::STATUS_REVOKED,
                'revoked_at' => now(),
            ])->save();

            $this->auditoria->atribuicao(OperatorSessionAudit::BRANCH_ACCESS_REVOKED, $atribuicao, $ator);

            // Na mesma transação: a revogação não pode ficar visível antes de
            // as sessões afetadas pararem de valer.
            $this->sessoes->revokeForAssignment($atribuicao, $ator);

            return $atribuicao;
        }, 3);
    }

    /**
     * @return Collection<int, PdvOperatorBranchAssignment>
     */
    public function forBranch(Branch $filial): Collection
    {
        return PdvOperatorBranchAssignment::consultaIsolada($filial->tenant_id)
            ->where('branch_id', $filial->id)
            ->with('user')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return Collection<int, PdvOperatorBranchAssignment>
     */
    public function forOperator(string $tenantId, User $operador): Collection
    {
        return PdvOperatorBranchAssignment::consultaIsolada($tenantId)
            ->where('user_id', $operador->id)
            ->with('branch')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * As duas autoridades, mais as condições estruturais da concessão.
     *
     * A ordem importa pouco para segurança e muito para a resposta: tudo que
     * falha aqui sai como 403 genérico, sem dizer se a pessoa existe, se tem
     * vínculo, ou qual das verificações recusou.
     *
     * @throws OperatorSessionFailed
     */
    private function exigirAutoridade(User $ator, User $operador, Branch $filial, bool $exigirVinculoAtivo = true): void
    {
        // Autoridade sobre a filial, incluindo a conferência de que ela
        // pertence ao estabelecimento resolvido na requisição.
        if (! Gate::forUser($ator)->allows('update', $filial)) {
            throw OperatorSessionFailed::proibido();
        }

        // Estrutura operacional: estabelecimento, empresa e filial ativos, e
        // coerentes entre si. Mesma definição que a máquina usa — operador não
        // pode ser atribuído a uma filial que um Terminal não poderia servir.
        $tenant = Tenant::whereKey($filial->tenant_id)->first();
        $company = Company::withoutGlobalScopes()->whereKey($filial->company_id)->first();

        if (! TerminalStructure::operacional($tenant, $company, $filial)
            || $company->tenant_id !== $filial->tenant_id) {
            throw OperatorSessionFailed::proibido();
        }

        // Vínculo ativo da pessoa com o estabelecimento. Conceder caixa a quem
        // não é da loja seria criar acesso cruzado entre estabelecimentos.
        if ($exigirVinculoAtivo
            && $operador->membershipFor($filial->tenant_id)?->status !== TenantUser::STATUS_ACTIVE) {
            throw OperatorSessionFailed::proibido();
        }

        $this->exigirDominioSobreAPessoa($ator, $operador, $filial->tenant_id);
    }

    /**
     * Contenção da delegação (SEC-04 · E2), com uma exceção justificada.
     *
     * A regra é a do UserAdministrationGuard: a autoridade dos papéis do alvo
     * precisa estar contida na do ator. O guard em si não pode ser chamado aqui
     * por um detalhe que o deixaria inutilizável — `access-pdv` é órfã de todos
     * os papéis padrão, inclusive do admin, de propósito. Com ela contando como
     * autoridade do alvo, ninguém administraria um operador: o administrador do
     * estabelecimento não tem `access-pdv` e seria recusado ao tentar revogar o
     * acesso que ele mesmo concedeu.
     *
     * A exceção é exatamente a permissão que ESTE endpoint administra, e só
     * ela. Exigir que o administrador também possa operar um caixa para poder
     * conceder caixa inverteria a relação: quem administra não precisa exercer.
     * Qualquer outra permissão do alvo continua tendo de estar contida — um
     * gerente segue sem conseguir mexer no acesso de um administrador.
     *
     * @throws OperatorSessionFailed
     */
    private function exigirDominioSobreAPessoa(User $ator, User $alvo, string $tenantId): void
    {
        $autoridadeDoAtor = $this->autoridade->autoridadeDoAtor($ator, $tenantId);

        $autoridadeDoAlvo = $this->autoridade->autoridadeDosPapeis(
            // Papéis do alvo em QUALQUER status de vínculo: um vínculo suspenso
            // pode ser reativado, e a autoridade latente dele volta a valer.
            $this->autoridade->papeisDoUsuario($alvo, $tenantId),
            $tenantId,
        );

        $administrada = Permission::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', OperatorPermission::ACESSO)
            ->value('id');

        $excedente = array_diff(
            $autoridadeDoAlvo,
            $autoridadeDoAtor,
            $administrada === null ? [] : [(string) $administrada],
        );

        if ($excedente !== []) {
            throw OperatorSessionFailed::proibido();
        }
    }
}
