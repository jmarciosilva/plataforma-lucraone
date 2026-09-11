<?php

namespace Tests\Feature\Security;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Concerns\MontaCenariosDeAutorizacao;
use Tests\TestCase;

/**
 * SEC-04 · E2 — data fix da matriz dos papéis padrão em instalações existentes.
 *
 * O seed corrigido já não atribui as permissões de filiais atribuídas ao
 * manager e ao user, mas estabelecimentos existentes ainda têm essas linhas. O
 * setUp recria esse estado em dois tenants; cada teste executa a migration e
 * confere um aspecto do resultado.
 *
 * A propriedade da matriz em si é protegida por StandardRoleMatrixSecurityTest.
 */
#[Group('sec-04')]
#[Group('sec-04-e2')]
class StandardRoleMatrixDataFixSecurityTest extends TestCase
{
    use MontaCenariosDeAutorizacao, RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_09_11_000000_remove_obsolete_assigned_branch_permissions_from_standard_roles.php';

    private const PERMISSOES_DE_FILIAL = ['manage-assigned-branches', 'view-assigned-branches'];

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->active()->create();
        $this->tenantB = Tenant::factory()->active()->create();
        $this->provisionarPapeisPadrao();

        // Estado de uma instalação anterior à correção do seed.
        foreach ($this->tenants() as $tenant) {
            $this->papel($tenant, 'manager')->grantPermission($this->permissao($tenant, 'manage-assigned-branches'));
            $this->papel($tenant, 'user')->grantPermission($this->permissao($tenant, 'view-assigned-branches'));
        }
    }

    public function test_manager_existente_perde_manage_assigned_branches_em_cada_tenant(): void
    {
        $this->assertDataFixRemoveSomente('manager', 'manage-assigned-branches');
    }

    public function test_user_existente_perde_view_assigned_branches_em_cada_tenant(): void
    {
        $this->assertDataFixRemoveSomente('user', 'view-assigned-branches');
    }

    public function test_papeis_personalizados_com_permissoes_de_filial_ficam_intactos(): void
    {
        $this->assertDataFixPreserva([
            $this->papelPersonalizado($this->tenantA, 'supervisor-de-filial', ['view-branches', 'manage-assigned-branches']),
            $this->papelPersonalizado($this->tenantA, 'leitor-de-filial', ['view-assigned-branches']),
            $this->papelPersonalizado($this->tenantB, 'gestor-de-filial', self::PERMISSOES_DE_FILIAL),
        ]);
    }

    /**
     * O filtro é pelo papel padrão, não pela permissão: admin e viewer com as
     * mesmas permissões não são tocados.
     */
    public function test_admin_e_viewer_ficam_intactos_mesmo_com_permissoes_de_filial(): void
    {
        $papeis = [];

        foreach ($this->tenants() as $tenant) {
            foreach (['admin', 'viewer'] as $nome) {
                $papel = $this->papel($tenant, $nome);

                foreach (self::PERMISSOES_DE_FILIAL as $permissao) {
                    $papel->grantPermission($this->permissao($tenant, $permissao));
                }

                $papeis[] = $papel;
            }
        }

        $this->assertDataFixPreserva($papeis);
    }

    /**
     * Linhas em que papel, permissão e role_permission não são do mesmo tenant
     * não vieram do seed, e o data fix não decide sobre elas.
     */
    public function test_linhas_com_tenant_divergente_nao_sao_removidas(): void
    {
        $managerA = $this->papel($this->tenantA, 'manager');
        $userA = $this->papel($this->tenantA, 'user');
        $agora = now();

        $divergentes = [
            // permissão de B gravada no manager de A com a linha de A
            ['role_id' => $managerA->id, 'permission_id' => $this->permissao($this->tenantB, 'manage-assigned-branches')->id, 'tenant_id' => $this->tenantA->id],
            // permissão de B gravada no manager de A com a linha de B
            ['role_id' => $managerA->id, 'permission_id' => $this->permissao($this->tenantB, 'manage-assigned-branches')->id, 'tenant_id' => $this->tenantB->id],
            // permissão de A gravada no user de A com a linha de B
            ['role_id' => $userA->id, 'permission_id' => $this->permissao($this->tenantA, 'view-assigned-branches')->id, 'tenant_id' => $this->tenantB->id],
        ];

        foreach ($divergentes as $linha) {
            DB::table('role_permission')->insert([...$linha, 'created_at' => $agora, 'updated_at' => $agora]);
        }

        $this->executarDataFix();

        foreach ($divergentes as $linha) {
            $this->assertDatabaseHas('role_permission', $linha);
        }

        // As linhas coerentes dos mesmos papéis saíram: o data fix rodou de fato.
        $this->assertNotContains($this->linha($this->tenantA, 'manage-assigned-branches'), $this->permissoesDoPapel($managerA));
        $this->assertNotContains($this->linha($this->tenantA, 'view-assigned-branches'), $this->permissoesDoPapel($userA));
    }

    public function test_catalogo_de_permissoes_e_platform_admin_ficam_intactos(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->forTenant($this->tenantA)->create();
        $platformAdmin->assignRole($this->papel($this->tenantA, 'manager'), $this->tenantA->id);
        $catalogoAntes = $this->idsDoCatalogo();
        $userRoleAntes = $this->linhasDeUserRole();

        $this->executarDataFix();

        $this->assertSame($catalogoAntes, $this->idsDoCatalogo());

        foreach ($this->tenants() as $tenant) {
            foreach (self::PERMISSOES_DE_FILIAL as $nome) {
                $this->assertTrue(
                    Permission::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', $nome)->exists(),
                    "{$nome} continua no catálogo do tenant"
                );
            }
        }

        $this->assertTrue($platformAdmin->fresh()->isPlatformAdmin());
        $this->assertSame($userRoleAntes, $this->linhasDeUserRole());
    }

    public function test_segunda_execucao_nao_altera_nada(): void
    {
        $this->papelPersonalizado($this->tenantA, 'supervisor-de-filial', self::PERMISSOES_DE_FILIAL);
        $migration = require base_path(self::MIGRATION);

        $migration->up();
        $depoisDaPrimeiraExecucao = $this->retrato();
        $migration->up();

        $this->assertSame(
            $depoisDaPrimeiraExecucao,
            $this->retrato(),
            'executar up() de novo não pode remover nem recriar nada'
        );
    }

    public function test_instalacao_existente_passa_a_satisfazer_a_contencao_da_matriz(): void
    {
        foreach ($this->tenants() as $tenant) {
            $this->assertNotSame(
                [],
                array_diff($this->nomesDasPermissoes($tenant, 'manager'), $this->nomesDasPermissoes($tenant, 'admin')),
                'pré-condição: antes do data fix o manager não cabe no admin'
            );
        }

        $this->executarDataFix();

        $pares = [['manager', 'admin'], ['user', 'manager'], ['user', 'admin'], ['viewer', 'manager'], ['viewer', 'admin']];

        foreach ($this->tenants() as $tenant) {
            foreach ($pares as [$contido, $dominante]) {
                $this->assertSame(
                    [],
                    array_values(array_diff($this->nomesDasPermissoes($tenant, $contido), $this->nomesDasPermissoes($tenant, $dominante))),
                    "{$contido} precisa caber em {$dominante} depois do data fix"
                );
            }
        }
    }

    public function test_migration_nao_depende_do_seeder_nem_de_classes_da_aplicacao(): void
    {
        $codigo = file_get_contents(base_path(self::MIGRATION));

        $this->assertStringNotContainsString('AuthorizationSeeder', $codigo);
        $this->assertDoesNotMatchRegularExpression('/^use\s+App\\\\/m', $codigo);
    }

    private function assertDataFixRemoveSomente(string $papel, string $permissao): void
    {
        $antes = [];

        foreach ($this->tenants() as $tenant) {
            $antes[$tenant->id] = $this->permissoesDoPapel($this->papel($tenant, $papel));
            $this->assertContains($this->linha($tenant, $permissao), $antes[$tenant->id], "pré-condição: {$papel} tem {$permissao}");
        }

        $this->executarDataFix();

        foreach ($this->tenants() as $tenant) {
            $this->assertSame(
                array_values(array_diff($antes[$tenant->id], [$this->linha($tenant, $permissao)])),
                $this->permissoesDoPapel($this->papel($tenant, $papel)),
                "{$papel} perde só {$permissao}, no próprio tenant"
            );
        }
    }

    /**
     * @param  array<int, Role>  $papeis
     */
    private function assertDataFixPreserva(array $papeis): void
    {
        $antes = array_map(fn (Role $papel) => $this->permissoesDoPapel($papel), $papeis);

        $this->executarDataFix();

        foreach ($papeis as $indice => $papel) {
            $this->assertNotEmpty($antes[$indice]);
            $this->assertSame($antes[$indice], $this->permissoesDoPapel($papel), "o papel {$papel->name} não pode ser alterado");
        }
    }

    private function executarDataFix(): void
    {
        (require base_path(self::MIGRATION))->up();
    }

    /**
     * @return array<int, Tenant>
     */
    private function tenants(): array
    {
        return [$this->tenantA, $this->tenantB];
    }

    private function linha(Tenant $tenant, string $permissao): string
    {
        return "{$this->permissao($tenant, $permissao)->id}@{$tenant->id}";
    }

    private function nomesDasPermissoes(Tenant $tenant, string $papel): array
    {
        return $this->papel($tenant, $papel)
            ->permissionsForTenant()
            ->where('permissions.tenant_id', $tenant->id)
            ->pluck('permissions.name')
            ->sort()
            ->values()
            ->all();
    }

    private function idsDoCatalogo(): array
    {
        return DB::table('permissions')->orderBy('id')->pluck('id')->all();
    }

    private function linhasDeUserRole(): array
    {
        return DB::table('user_role')
            ->orderBy('id')
            ->get(['user_id', 'role_id', 'tenant_id'])
            ->map(fn (object $linha) => "{$linha->user_id}:{$linha->role_id}@{$linha->tenant_id}")
            ->all();
    }

    private function retrato(): array
    {
        return [
            'role_permission' => DB::table('role_permission')
                ->orderBy('id')
                ->get(['id', 'role_id', 'permission_id', 'tenant_id'])
                ->map(fn (object $linha) => (array) $linha)
                ->all(),
            'permissions' => $this->idsDoCatalogo(),
            'user_role' => $this->linhasDeUserRole(),
        ];
    }
}
