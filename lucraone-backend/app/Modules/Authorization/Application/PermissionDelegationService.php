<?php

namespace App\Modules\Authorization\Application;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Collection;

/**
 * Contenção da delegação de permissões (SEC-04 · E1).
 *
 * A RolePolicy decide quem entra no fluxo de update-role. Este serviço decide
 * se a transformação pedida é delegável por quem a executa:
 *
 *   - o próprio papel não é sincronizado, qualquer que seja o payload — cobre
 *     autoelevação, lockout e alteração ambígua da própria autoridade;
 *   - toda permissão adicionada ou removida precisa estar entre as que o ator
 *     possui. Retirar poder também é exercer autoridade.
 *
 * A comparação é entre o estado atual e o desejado do papel, não entre o
 * payload e o ator: a sincronização substitui o conjunto inteiro, e uma
 * permissão reenviada sem alteração não é delegada por ninguém.
 *
 * Tudo é avaliado no estabelecimento do papel, e não no TenantContext da
 * requisição.
 */
class PermissionDelegationService
{
    public const PROPRIO_PAPEL = 'não é possível alterar as permissões do seu próprio papel.';

    public const FORA_DA_AUTORIDADE = 'você só pode conceder ou retirar permissões que possui. fora da sua autoridade: ';

    /**
     * @param  array<int, string>  $permissoesDesejadas  ids do conjunto completo que o papel deve ter
     *
     * @throws PermissionDelegationDenied
     */
    public function garantirSincronizacao(User $ator, Role $papel, array $permissoesDesejadas): void
    {
        $tenantId = $papel->tenant_id;

        if ($ator->hasRole($papel, $tenantId)) {
            throw new PermissionDelegationDenied(self::PROPRIO_PAPEL);
        }

        $atuais = $papel->permissionsForTenant()->pluck('permissions.id');
        $desejadas = collect($permissoesDesejadas)->map(fn ($id) => (string) $id)->unique();

        $adicionadas = $desejadas->diff($atuais);
        $removidas = $atuais->diff($desejadas);

        $foraDaAutoridade = $adicionadas
            ->merge($removidas)
            ->diff($this->permissoesDoAtor($ator, $tenantId));

        if ($foraDaAutoridade->isEmpty()) {
            return;
        }

        $nomes = Permission::withoutGlobalScopes()
            ->whereIn('id', $foraDaAutoridade->all())
            ->orderBy('name')
            ->pluck('name');

        throw new PermissionDelegationDenied(self::FORA_DA_AUTORIDADE.$nomes->implode(', ').'.');
    }

    /**
     * Permissões que o ator efetivamente possui no estabelecimento informado.
     *
     * Papel, vínculo do papel e linha de permissão precisam ser todos desse
     * estabelecimento, para que nada gravado com outro contexto conte como
     * autoridade aqui.
     */
    private function permissoesDoAtor(User $ator, string $tenantId): Collection
    {
        if (! $ator->canAccessTenant($tenantId)) {
            return collect();
        }

        return $ator->rolesForTenant($tenantId)
            ->where('roles.tenant_id', $tenantId)
            ->with(['permissions' => fn ($query) => $query
                ->wherePivot('tenant_id', $tenantId)
                ->where('permissions.tenant_id', $tenantId)])
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('id'))
            ->unique()
            ->values();
    }
}
