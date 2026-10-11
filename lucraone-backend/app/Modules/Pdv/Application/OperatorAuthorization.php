<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorProfile;
use App\Modules\Pdv\Domain\OperatorPermission;
use App\Modules\Terminals\Application\TerminalAuthenticationEligibility;
use App\Modules\Terminals\Domain\Models\Terminal;

/**
 * Esta pessoa pode operar ESTE caixa agora?
 *
 * Definição única, consultada no login, na revalidação, em toda requisição
 * protegida por sessão operacional e na emissão do lease offline. Sem isso a
 * mesma regra existiria em quatro lugares e divergiria no primeiro ajuste — é o
 * mesmo motivo pelo qual TerminalAuthenticationEligibility existe para a
 * máquina.
 *
 * A avaliação é POR REQUISIÇÃO e lê o estado atual do banco. Nada é inferido do
 * que valia no login: desativar a conta, suspender o vínculo, revogar a filial
 * ou retirar `access-pdv` precisa derrubar a sessão na requisição seguinte, sem
 * depender de nenhum evento ter rodado. Os eventos (revogação em cascata)
 * antecipam o efeito; não são a garantia.
 *
 * As condições são conjuntivas e não há atalho por papel: um administrador do
 * estabelecimento sem perfil de caixa e sem atribuição ativa à filial não opera
 * caixa. Autoridade para administrar não é autoridade para operar.
 */
class OperatorAuthorization
{
    public function __construct(private TerminalAuthenticationEligibility $elegibilidade) {}

    /**
     * O perfil de caixa ATIVO desta pessoa no estabelecimento do Terminal.
     *
     * Terceiro degrau da escada de status, verificado por requisição como os
     * outros: desativar um operador tem de derrubar a sessão dele na requisição
     * seguinte, sem depender de nenhum evento.
     */
    public function perfilAtivo(User $operador, Terminal $terminal): ?PdvOperatorProfile
    {
        $perfil = PdvOperatorProfile::para($terminal->tenant_id, $operador->id);

        return $perfil !== null && $perfil->isActive() ? $perfil : null;
    }

    /**
     * A atribuição ativa que autoriza esta pessoa neste Terminal, ou null.
     *
     * Devolve o registro, e não bool, porque quem chama precisa dele: o lease
     * offline usa a identidade e o instante de atualização da atribuição para
     * compor a versão de autorização.
     */
    public function atribuicaoAtiva(User $operador, Terminal $terminal): ?PdvOperatorBranchAssignment
    {
        if (! $this->elegibilidade->allows($terminal)) {
            return null;
        }

        // Perfil de caixa ativo: sem ele a pessoa não é operadora aqui, mesmo
        // com conta, vínculo, permissão e atribuição em ordem.
        if ($this->perfilAtivo($operador, $terminal) === null) {
            return null;
        }

        // Conta ativa E vínculo ativo com o estabelecimento DO TERMINAL. O
        // estabelecimento nunca vem de cabeçalho nem do corpo: quem o fixa é o
        // vínculo do Terminal autenticado.
        if (! $operador->isActive() || ! $operador->canAccessTenant($terminal->tenant_id)) {
            return null;
        }

        // Permissão do RBAC do estabelecimento, consultada com tenant
        // explícito — o HasRole aceita o parâmetro justamente para não depender
        // do contexto da requisição.
        if (! $operador->hasPermission(OperatorPermission::ACESSO, $terminal->tenant_id)) {
            return null;
        }

        return PdvOperatorBranchAssignment::ativaPara(
            $terminal->tenant_id,
            $operador->id,
            $terminal->branch_id,
        );
    }

    public function autoriza(User $operador, Terminal $terminal): bool
    {
        return $this->atribuicaoAtiva($operador, $terminal) !== null;
    }

    /**
     * Impressão digital da autorização efetiva.
     *
     * NÃO é um contador. É um resumo determinístico do estado que autoriza: se
     * qualquer parte dele mudar — status da conta, do vínculo, da atribuição, da
     * máquina, ou a lista de permissões —, o valor muda, e um cliente offline
     * que guardou o valor anterior sabe que a autorização dele ficou velha. Um
     * contador exigiria uma coluna monotônica em cada uma dessas tabelas, e
     * quem esquecesse de incrementá-la criaria uma janela silenciosa.
     *
     * Os insumos são identificadores e estados, nunca segredo: o valor viaja
     * dentro do lease, que é legível pelo cliente.
     */
    public function versao(User $operador, Terminal $terminal, PdvOperatorBranchAssignment $atribuicao): string
    {
        $permissoes = $operador->getPermissions($terminal->tenant_id)
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $perfil = PdvOperatorProfile::para($terminal->tenant_id, $operador->id);

        $insumos = [
            'u' => $operador->id,
            'us' => $operador->status,
            'm' => $operador->membershipFor($terminal->tenant_id)?->status,
            'a' => $atribuicao->id,
            'as' => $atribuicao->status,
            'au' => $atribuicao->updated_at?->getTimestamp(),
            // Perfil de caixa: trocar o username ou desativar o operador muda a
            // autorização efetiva, e um cliente offline precisa perceber.
            'o' => $perfil?->id,
            'ou' => $perfil?->username_canonical,
            'os' => $perfil?->status,
            'oupd' => $perfil?->updated_at?->getTimestamp(),
            't' => $terminal->id,
            'ts' => $terminal->status,
            'p' => $permissoes,
        ];

        return substr(hash('sha256', (string) json_encode($insumos)), 0, 32);
    }
}
