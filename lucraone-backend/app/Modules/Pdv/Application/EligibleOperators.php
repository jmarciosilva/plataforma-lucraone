<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorProfile;
use App\Modules\Pdv\Domain\OperatorPermission;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Quem pode operar ESTE caixa, para a tela de seleção do PDV.
 *
 * É a mesma pergunta que `OperatorAuthorization` responde no login, mas no
 * plural e sem senha — e é por isso que ela vive aqui e não lá: aquela classe
 * decide sobre UMA pessoa e precisa ser exata a cada requisição; esta monta uma
 * LISTA e precisa ser uma consulta só.
 *
 * As condições são as mesmas, na mesma ordem de importância: perfil de caixa
 * ativo, vínculo ativo com o estabelecimento, conta ativa, permissão
 * `access-pdv` por papel no estabelecimento e atribuição ativa à filial DO
 * TERMINAL. Uma lista mais generosa que o login seria pior que não ter lista:
 * ofereceria ao operador um nome que vai recusar a senha dele.
 *
 * A listagem NÃO autentica ninguém. Ela existe para poupar digitação; a senha
 * continua obrigatória, e o campo de username continua aceitando digitação
 * manual — um operador recém-cadastrado que ainda não apareça numa lista em
 * cache precisa poder entrar.
 *
 * A permissão é avaliada em SQL, por junção de papéis, e não com
 * `hasPermission()` por pessoa: a versão ingênua faria uma consulta por
 * operador, e um supermercado tem dezenas.
 */
class EligibleOperators
{
    /**
     * @return LengthAwarePaginator<int, object>
     */
    public function paraTerminal(Terminal $terminal, int $porPagina = 50, int $pagina = 1): LengthAwarePaginator
    {
        $tenantId = $terminal->tenant_id;

        $comPermissao = DB::table('user_role')
            ->join('roles', 'roles.id', '=', 'user_role.role_id')
            ->join('role_permission', 'role_permission.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('user_role.tenant_id', $tenantId)
            ->where('roles.tenant_id', $tenantId)
            ->where('role_permission.tenant_id', $tenantId)
            ->where('permissions.tenant_id', $tenantId)
            ->where('permissions.name', OperatorPermission::ACESSO)
            ->select('user_role.user_id');

        return DB::table('pdv_operator_profiles as perfil')
            ->join('users', 'users.id', '=', 'perfil.user_id')
            ->join('tenant_user as vinculo', function ($join) use ($tenantId) {
                $join->on('vinculo.user_id', '=', 'perfil.user_id')
                    ->where('vinculo.tenant_id', '=', $tenantId);
            })
            ->join('pdv_operator_branch_assignments as atribuicao', function ($join) use ($tenantId, $terminal) {
                $join->on('atribuicao.user_id', '=', 'perfil.user_id')
                    ->where('atribuicao.tenant_id', '=', $tenantId)
                    ->where('atribuicao.branch_id', '=', $terminal->branch_id)
                    ->where('atribuicao.status', '=', PdvOperatorBranchAssignment::STATUS_ACTIVE);
            })
            ->where('perfil.tenant_id', $tenantId)
            ->where('perfil.status', PdvOperatorProfile::STATUS_ACTIVE)
            ->where('vinculo.status', TenantUser::STATUS_ACTIVE)
            ->where('users.status', 'ACTIVE')
            // Identidade arquivada não aparece: o soft delete do User não é
            // aplicado por consulta crua, então o filtro é explícito.
            ->whereNull('users.deleted_at')
            ->whereIn('perfil.user_id', $comPermissao)
            // Campo por campo. Nada de `select('*')`: `users` tem senha, hash,
            // remember_token e e-mail, e nenhum deles pertence a esta tela.
            ->select([
                'perfil.user_id as id',
                'users.name as display_name',
                'perfil.username as username',
            ])
            ->orderBy('users.name')
            ->orderBy('perfil.username')
            ->paginate(perPage: $porPagina, page: $pagina);
    }
}
