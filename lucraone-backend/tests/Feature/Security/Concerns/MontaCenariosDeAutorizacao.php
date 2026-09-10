<?php

namespace Tests\Feature\Security\Concerns;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Cenários de autorização da baseline do SEC-04.
 *
 * Os papéis vêm do AuthorizationSeeder de propósito: os vetores dependem do
 * que os papéis padrão carregam hoje — o manager com manage-users, o admin com
 * create-role. Fixtures escritas à mão poderiam divergir do que roda de fato e
 * esconder o problema.
 *
 * O seeder só provisiona os estabelecimentos que já existem, então eles
 * precisam ser criados antes de provisionarPapeisPadrao().
 */
trait MontaCenariosDeAutorizacao
{
    protected function provisionarPapeisPadrao(): void
    {
        $this->seed(AuthorizationSeeder::class);
    }

    protected function papel(Tenant $tenant, string $nome): Role
    {
        return Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', $nome)
            ->firstOrFail();
    }

    protected function permissao(Tenant $tenant, string $nome): Permission
    {
        return Permission::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', $nome)
            ->firstOrFail();
    }

    /**
     * Pessoa com vínculo ativo no estabelecimento e um papel padrão nele.
     */
    protected function membroComPapel(Tenant $tenant, string $papel, array $atributos = []): User
    {
        $usuario = User::factory()->forTenant($tenant)->create($atributos);
        $usuario->assignRole($this->papel($tenant, $papel), $tenant->id);

        return $usuario;
    }

    /**
     * Papel fora do seed, com exatamente as permissões informadas.
     */
    protected function papelPersonalizado(Tenant $tenant, string $nome, array $permissoes): Role
    {
        $papel = Role::factory()->forTenant($tenant->id)->create(['name' => $nome]);

        foreach ($permissoes as $nomeDaPermissao) {
            $papel->grantPermission($this->permissao($tenant, $nomeDaPermissao));
        }

        return $papel;
    }

    /**
     * Retrato das linhas de role_permission do papel, em qualquer estabelecimento.
     *
     * Inclui o tenant de cada linha: gravar no papel uma permissão de outro
     * estabelecimento também é alteração, mesmo quando não concede nada.
     */
    protected function permissoesDoPapel(Role $papel): array
    {
        return DB::table('role_permission')
            ->where('role_id', $papel->id)
            ->orderBy('permission_id')
            ->orderBy('tenant_id')
            ->get(['permission_id', 'tenant_id'])
            ->map(fn ($linha) => "{$linha->permission_id}@{$linha->tenant_id}")
            ->all();
    }
}
