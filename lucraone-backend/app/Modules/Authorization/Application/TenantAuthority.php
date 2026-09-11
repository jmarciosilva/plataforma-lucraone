<?php

namespace App\Modules\Authorization\Application;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Autoridade efetiva num estabelecimento informado explicitamente (SEC-04).
 *
 * Autoridade é o conjunto de ids de permissão alcançado pelos papéis. Toda
 * consulta filtra papel, vínculo do papel, linha de permissão e permissão pelo
 * mesmo estabelecimento, e nada aqui lê o TenantContext da requisição: quem
 * chama decide o estabelecimento.
 *
 * São duas perguntas diferentes, que não se misturam:
 *
 *   - autoridadeDoAtor: o que a pessoa pode exercer agora. Exige conta e
 *     vínculo ativos; sem isso, nenhuma;
 *   - papeisDoUsuario e autoridadeDosPapeis: o que os papéis representam, com
 *     qualquer status de vínculo. É a autoridade latente de um alvo suspenso,
 *     que volta a valer se o vínculo for reativado.
 *
 * Os métodos filtram e não recusam: o que fazer com um id que não pertence ao
 * estabelecimento é decisão de quem chama.
 *
 * Todos devolvem ids em string, sem duplicatas e ordenados.
 */
class TenantAuthority
{
    /**
     * @return list<string> ids de permissão
     */
    public function autoridadeDoAtor(User $usuario, string $tenantId): array
    {
        if (! $usuario->canAccessTenant($tenantId)) {
            return [];
        }

        return $this->autoridadeDosPapeis($this->papeisDoUsuario($usuario, $tenantId), $tenantId);
    }

    /**
     * Papéis atribuídos à pessoa no estabelecimento, qualquer que seja o status
     * do vínculo.
     *
     * @return list<string> ids de papel
     */
    public function papeisDoUsuario(User $usuario, string $tenantId): array
    {
        return $this->normalizar(
            DB::table('user_role')
                ->join('roles', 'roles.id', '=', 'user_role.role_id')
                ->where('user_role.user_id', $usuario->id)
                ->where('user_role.tenant_id', $tenantId)
                ->where('roles.tenant_id', $tenantId)
                ->pluck('user_role.role_id')
                ->all()
        );
    }

    /**
     * Só os ids informados que são papéis deste estabelecimento.
     *
     * @param  array<int, string>  $roleIds
     * @return list<string> ids de papel
     */
    public function papeisDoTenant(array $roleIds, string $tenantId): array
    {
        $ids = $this->normalizar($roleIds);

        if ($ids === []) {
            return [];
        }

        return $this->normalizar(
            DB::table('roles')
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->all()
        );
    }

    /**
     * União das permissões dos papéis informados neste estabelecimento. Papel
     * de outro estabelecimento ou inexistente não contribui com nada.
     *
     * @param  array<int, string>  $roleIds
     * @return list<string> ids de permissão
     */
    public function autoridadeDosPapeis(array $roleIds, string $tenantId): array
    {
        $ids = $this->normalizar($roleIds);

        if ($ids === []) {
            return [];
        }

        return $this->normalizar(
            DB::table('role_permission')
                ->join('roles', 'roles.id', '=', 'role_permission.role_id')
                ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
                ->whereIn('role_permission.role_id', $ids)
                ->where('roles.tenant_id', $tenantId)
                ->where('role_permission.tenant_id', $tenantId)
                ->where('permissions.tenant_id', $tenantId)
                ->pluck('role_permission.permission_id')
                ->all()
        );
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<string>
     */
    private function normalizar(array $ids): array
    {
        $normalizados = array_values(array_unique(array_map('strval', $ids)));
        sort($normalizados, SORT_STRING);

        return $normalizados;
    }
}
