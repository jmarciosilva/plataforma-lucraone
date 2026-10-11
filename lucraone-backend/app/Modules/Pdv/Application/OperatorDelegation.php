<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Authorization\Application\TenantAuthority;
use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Domain\Exceptions\OperatorSessionFailed;
use App\Modules\Pdv\Domain\OperatorPermission;
use App\Modules\Tenancy\Application\TenantContext;

/**
 * Quem pode administrar o acesso de quem, no PDV.
 *
 * Duas autoridades, exigidas juntas e sem substituição entre si:
 *
 *   - sobre a ESTRUTURA, `manage-branches` no estabelecimento — a mesma
 *     autoridade que o TerminalPolicy já pede para administrar Terminal. Quem
 *     cuida das filiais cuida de quem opera nelas;
 *   - sobre a PESSOA, a contenção do SEC-04 · E2: a autoridade dos papéis do
 *     alvo precisa estar contida na do ator. É o que impede um gerente de mexer
 *     no acesso de um administrador.
 *
 * A contenção tem UMA exceção, e ela é necessária: `access-pdv` é órfã de todos
 * os papéis padrão, inclusive do admin. Contando como autoridade do alvo,
 * ninguém administraria um operador — o administrador do estabelecimento seria
 * recusado ao desativar o caixa que ele mesmo cadastrou. A exceção é exatamente
 * a permissão administrada, e só ela; qualquer outra permissão do alvo continua
 * tendo de estar contida.
 *
 * Extraída de OperatorBranchAccess quando a administração pela interface web
 * passou a precisar da mesma regra. Uma única definição: duas cópias de uma
 * contenção de delegação divergiriam no primeiro ajuste, e a divergência
 * abriria um caminho de escalada silencioso.
 *
 * Nenhum papel dá passe livre: Platform Admin, nome de papel e permissão isolada
 * não dispensam as verificações acima.
 */
class OperatorDelegation
{
    public function __construct(private TenantAuthority $autoridade) {}

    /**
     * O ator pode administrar operadores deste estabelecimento?
     */
    public function podeAdministrar(User $ator, string $tenantId): bool
    {
        return $ator->canAccessTenant($tenantId)
            && $ator->hasPermission(OperatorPermission::ADMINISTRAR_ATRIBUICOES, $tenantId);
    }

    /**
     * O ator pode apenas consultar?
     */
    public function podeConsultar(User $ator, string $tenantId): bool
    {
        return $ator->canAccessTenant($tenantId)
            && $ator->hasAnyPermission(OperatorPermission::CONSULTAR_ATRIBUICOES, $tenantId);
    }

    /**
     * Exige as duas autoridades sobre uma pessoa específica.
     *
     * @throws OperatorSessionFailed
     */
    public function exigir(User $ator, ?User $alvo, string $tenantId): void
    {
        if (! $this->podeAdministrar($ator, $tenantId)) {
            throw OperatorSessionFailed::proibido();
        }

        if ($alvo !== null) {
            $this->exigirDominioSobreAPessoa($ator, $alvo, $tenantId);
        }
    }

    /**
     * Contenção da delegação (SEC-04 · E2), com a exceção justificada acima.
     *
     * @throws OperatorSessionFailed
     */
    public function exigirDominioSobreAPessoa(User $ator, User $alvo, string $tenantId): void
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

    /**
     * O estabelecimento da requisição, para quem não o recebe por parâmetro.
     */
    public function tenantAtual(): string
    {
        return app(TenantContext::class)->id();
    }
}
