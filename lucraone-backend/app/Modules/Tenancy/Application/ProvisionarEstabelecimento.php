<?php

namespace App\Modules\Tenancy\Application;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Authorization\Domain\StandardRoleMatrix;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Provisiona a autorização padrão de um estabelecimento (ONB-01A).
 *
 * Fonte única consumida pelo TenantController e pelo AuthorizationSeeder, que
 * antes implementavam a mesma intenção de dois jeitos diferentes.
 *
 * Duas responsabilidades separadas de propósito: o seeder provisiona a matriz
 * de vários estabelecimentos e **não tem** um usuário a quem dar admin, então
 * exigir usuário aqui inviabilizaria o caso dele.
 *
 * Não depende do TenantContext: o estabelecimento vem por parâmetro e todas as
 * consultas usam `withoutGlobalScopes()`, porque o provisionamento acontece
 * fora de um contexto resolvido — na criação do próprio estabelecimento, num
 * seeder ou num comando.
 *
 * O escopo é autorização. Company, categoria, produto, preço, estoque, convite
 * e senha inicial não entram aqui.
 */
class ProvisionarEstabelecimento
{
    /**
     * Cria o catálogo de permissões e os papéis padrão do estabelecimento.
     *
     * Idempotente: rodar de novo não duplica permissão, papel nem concessão.
     */
    public function provisionarMatriz(Tenant $tenant): void
    {
        $permissoes = $this->criarPermissoes($tenant);

        foreach (StandardRoleMatrix::papeis() as $nome => $definicao) {
            $papel = $this->criarPapel($tenant, $nome, $definicao['descricao']);

            foreach ($definicao['permissoes'] as $nomeDaPermissao) {
                if (isset($permissoes[$nomeDaPermissao])) {
                    $papel->grantPermission($permissoes[$nomeDaPermissao]);
                }
            }
        }
    }

    /**
     * Dá a esta pessoa vínculo ativo e o papel de administrador no
     * estabelecimento. Exige a matriz já provisionada.
     */
    public function atribuirAdministrador(Tenant $tenant, User $usuario): void
    {
        $admin = Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'admin')
            ->firstOrFail();

        $usuario->joinTenant($tenant->id, TenantUser::STATUS_ACTIVE);
        $usuario->assignRole($admin, $tenant->id);
    }

    /**
     * @return array<string, Permission>
     */
    private function criarPermissoes(Tenant $tenant): array
    {
        $permissoes = [];

        foreach (StandardRoleMatrix::CATALOGO as $nome) {
            $permissoes[$nome] = Permission::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $nome],
                ['id' => (string) Str::ulid(), 'description' => $nome]
            );
        }

        return $permissoes;
    }

    private function criarPapel(Tenant $tenant, string $nome, string $descricao): Role
    {
        return Role::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $nome],
            ['id' => (string) Str::ulid(), 'description' => $descricao]
        );
    }
}
