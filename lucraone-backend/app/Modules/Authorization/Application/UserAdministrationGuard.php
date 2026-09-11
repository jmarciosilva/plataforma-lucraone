<?php

namespace App\Modules\Authorization\Application;

use App\Modules\Identity\Domain\Models\User;

/**
 * Contenção da administração de usuários (SEC-04 · E2).
 *
 * A UserPolicy decide quem entra nos fluxos de manage-users. Este guard decide
 * se o ator pode exercer a operação sobre esta pessoa, neste estabelecimento:
 *
 *   - administrar alguém exige dominar a autoridade dele: as permissões dos
 *     papéis que a pessoa tem no estabelecimento, qualquer que seja o status do
 *     vínculo, precisam estar todas entre as do ator. O mesmo endpoint altera
 *     credenciais, vínculo e papéis, então um papel preservado não é exceção;
 *   - atribuir papéis exige que todos existam no estabelecimento e que o ator
 *     domine a autoridade de todos. A operação é indivisível;
 *   - o próprio conjunto de papéis não muda: só é aceito reenviar o mesmo.
 *
 * Dominar é conter: autoridade vazia é sempre dominada. A autoridade vem de
 * TenantAuthority, sempre no estabelecimento informado, e nada é gravado aqui.
 */
class UserAdministrationGuard
{
    public const SEM_AUTORIDADE = 'você não tem autoridade para administrar o acesso desta pessoa.';

    public const PROPRIOS_PAPEIS = 'não é possível alterar os seus próprios papéis.';

    public function __construct(
        private TenantAuthority $autoridade
    ) {}

    /**
     * @param  array<int, string>  $papeisDesejados  ids dos papéis da pessoa cadastrada
     *
     * @throws UserAdministrationDenied
     */
    public function garantirCadastro(User $ator, string $tenantId, array $papeisDesejados): void
    {
        $desejados = $this->papeisDoEstabelecimento($papeisDesejados, $tenantId);

        $this->exigirDominio($this->autoridade->autoridadeDoAtor($ator, $tenantId), $desejados, $tenantId);
    }

    /**
     * @param  array<int, string>  $papeisDesejados  conjunto completo que a pessoa deve ter
     *
     * @throws UserAdministrationDenied
     */
    public function garantirEdicao(User $ator, User $alvo, string $tenantId, array $papeisDesejados): void
    {
        $desejados = $this->papeisDoEstabelecimento($papeisDesejados, $tenantId);
        $atuais = $this->autoridade->papeisDoUsuario($alvo, $tenantId);

        if ($ator->is($alvo)) {
            if ($desejados !== $atuais) {
                throw new UserAdministrationDenied(self::PROPRIOS_PAPEIS);
            }

            return;
        }

        $autoridadeDoAtor = $this->autoridade->autoridadeDoAtor($ator, $tenantId);

        $this->exigirDominio($autoridadeDoAtor, $atuais, $tenantId);
        $this->exigirDominio($autoridadeDoAtor, $desejados, $tenantId);
    }

    /**
     * Operações sobre a pessoa que não mexem nos papéis: arquivar, redefinir senha.
     *
     * @throws UserAdministrationDenied
     */
    public function garantirAdministracao(User $ator, User $alvo, string $tenantId): void
    {
        $this->exigirDominio(
            $this->autoridade->autoridadeDoAtor($ator, $tenantId),
            $this->autoridade->papeisDoUsuario($alvo, $tenantId),
            $tenantId
        );
    }

    /**
     * Papéis normalizados. Um id que não é papel do estabelecimento recusa a
     * operação inteira, em vez de ser ignorado.
     *
     * @param  array<int, mixed>  $papeis
     * @return list<string>
     */
    private function papeisDoEstabelecimento(array $papeis, string $tenantId): array
    {
        $normalizados = array_values(array_unique(array_map('strval', $papeis)));
        sort($normalizados, SORT_STRING);

        if ($normalizados !== $this->autoridade->papeisDoTenant($normalizados, $tenantId)) {
            throw new UserAdministrationDenied(self::SEM_AUTORIDADE);
        }

        return $normalizados;
    }

    /**
     * @param  list<string>  $autoridadeDoAtor
     * @param  list<string>  $papeis
     */
    private function exigirDominio(array $autoridadeDoAtor, array $papeis, string $tenantId): void
    {
        $foraDaAutoridade = array_diff($this->autoridade->autoridadeDosPapeis($papeis, $tenantId), $autoridadeDoAtor);

        if ($foraDaAutoridade !== []) {
            throw new UserAdministrationDenied(self::SEM_AUTORIDADE);
        }
    }
}
