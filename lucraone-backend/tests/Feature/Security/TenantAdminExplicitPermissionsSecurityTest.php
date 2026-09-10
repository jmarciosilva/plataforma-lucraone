<?php

namespace Tests\Feature\Security;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Reporting\Infrastructure\Mail\SalesSummaryMail;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClassConstant;
use Tests\Feature\Security\Concerns\MontaCenariosDeAutorizacao;
use Tests\TestCase;

/**
 * SEC-04 · regressão da matriz explícita do admin de tenant.
 *
 * O admin padrão deve operar os módulos de negócio pelas permissões explícitas
 * do seu papel, sem depender de create-role como coringa.
 */
#[Group('sec-04')]
#[Group('sec-04-create-role')]
class TenantAdminExplicitPermissionsSecurityTest extends TestCase
{
    use MontaCenariosDeAutorizacao, RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_09_10_000001_grant_explicit_business_permissions_to_admin_roles.php';

    /**
     * O que a migration de 2026-09-10 aplica. Fica literal de propósito: a
     * migration é histórica e não acompanha AdminPermissionMatrix.
     */
    private const SNAPSHOT_DA_MIGRATION = [
        'create-role', 'update-role', 'delete-role', 'view-roles',
        'create-permission', 'update-permission', 'delete-permission', 'view-permissions',
        'manage-companies', 'view-companies',
        'manage-products', 'view-products',
        'manage-inventory', 'view-inventory',
        'manage-sales', 'view-sales',
        'manage-customers', 'view-customers',
        'view-reports',
        'manage-automations', 'view-automations',
        'manage-users', 'view-users',
        'manage-branches', 'view-branches', 'view-all-branches',
    ];

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create(['timezone' => 'UTC']);
        $this->provisionarPapeisPadrao();
        $this->admin = $this->membroComPapel($this->tenant, 'admin');
    }

    public static function paginasDosModulos(): array
    {
        return [
            'produtos' => ['catalog.products.index'],
            'categorias' => ['catalog.categories.index'],
            'estoque' => ['inventory.index'],
            'pedidos' => ['sales.orders.index'],
            'clientes' => ['sales.customers.index'],
            'empresas' => ['companies.index'],
            'automações' => ['automations.index'],
            'relatórios' => ['reports.index'],
        ];
    }

    #[DataProvider('paginasDosModulos')]
    public function test_admin_de_tenant_acessa_modulos_por_permissoes_explicitas(string $rota): void
    {
        $this->actingAs($this->admin)
            ->get(route($rota))
            ->assertOk();
    }

    public function test_admin_de_tenant_recebe_resumo_por_view_reports_explicita(): void
    {
        Mail::fake();

        $this->artisan('relatorios:enviar-resumo', ['--tenant' => $this->tenant->id])
            ->assertSuccessful();

        Mail::assertQueued(
            SalesSummaryMail::class,
            fn (SalesSummaryMail $mail) => $mail->hasTo($this->admin->email)
        );
    }

    public function test_migration_complementa_apenas_admin_existente_de_forma_idempotente(): void
    {
        $legacyTenant = Tenant::factory()->active()->create();
        $outroTenant = Tenant::factory()->active()->create();
        $tenantSemAdmin = Tenant::factory()->active()->create();

        $legacyAdmin = Role::factory()->forTenant($legacyTenant->id)->create(['name' => 'admin-legado']);
        $manager = Role::factory()->forTenant($legacyTenant->id)->create(['name' => 'manager']);
        $adminOficial = Role::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $legacyTenant->id, 'name' => 'admin'],
            ['id' => (string) Str::ulid(), 'description' => 'Administrator role with full access']
        );
        $adminDoOutroTenant = Role::factory()->forTenant($outroTenant->id)->create(['name' => 'admin']);

        $createRoleLegada = Permission::factory()->forTenant($legacyTenant->id)->create(['name' => 'create-role']);
        $legacyAdmin->grantPermission($createRoleLegada);

        // Permissão fora da matriz: o data fix é aditivo e não pode removê-la.
        $permissaoExtra = Permission::factory()->forTenant($legacyTenant->id)->create(['name' => 'permissao-fora-da-matriz']);
        $adminOficial->grantPermission($permissaoExtra);

        $papelPersonalizadoAntes = $this->permissoesDoPapel($legacyAdmin);
        $permissoesDoTenantSemAdminAntes = Permission::withoutGlobalScopes()->where('tenant_id', $tenantSemAdmin->id)->count();
        $platformAdminsAntes = DB::table('users')->where('is_platform_admin', true)->count();

        $migration = require base_path(self::MIGRATION);
        $migration->up();
        $depoisDaPrimeiraExecucao = $this->retratoDaMigration($adminOficial, $adminDoOutroTenant);
        $migration->up();

        $this->assertSame(
            $depoisDaPrimeiraExecucao,
            $this->retratoDaMigration($adminOficial, $adminDoOutroTenant),
            'executar up() de novo não pode duplicar nem alterar nada'
        );

        $adminOficial = $adminOficial->fresh();

        foreach (self::SNAPSHOT_DA_MIGRATION as $name) {
            $permissaoDoSnapshot = Permission::withoutGlobalScopes()
                ->where('tenant_id', $legacyTenant->id)
                ->where('name', $name)
                ->firstOrFail();

            $this->assertTrue($adminOficial->hasPermission($permissaoDoSnapshot));
        }

        $this->assertPapelRecebeuSnapshot($adminDoOutroTenant);
        $this->assertCount(27, $this->permissoesDoPapel($adminOficial));
        $this->assertCount(26, $this->permissoesDoPapel($adminDoOutroTenant));
        $this->assertTrue($adminOficial->hasPermission($permissaoExtra));
        $this->assertTrue(
            $adminOficial->hasPermission($createRoleLegada),
            'a create-role já existente no tenant deve ser reaproveitada'
        );

        $this->assertSame(0, $this->linhasForaDoProprioTenant($adminOficial));
        $this->assertSame(0, $this->linhasForaDoProprioTenant($adminDoOutroTenant));

        $this->assertSame($papelPersonalizadoAntes, $this->permissoesDoPapel($legacyAdmin));
        $this->assertFalse(
            $legacyAdmin->fresh()->hasPermission(
                Permission::withoutGlobalScopes()
                    ->where('tenant_id', $legacyTenant->id)
                    ->where('name', 'manage-sales')
                    ->firstOrFail()
            )
        );
        $this->assertSame([], $this->permissoesDoPapel($manager));

        $this->assertSame(
            $permissoesDoTenantSemAdminAntes,
            Permission::withoutGlobalScopes()->where('tenant_id', $tenantSemAdmin->id)->count()
        );
        $this->assertSame($platformAdminsAntes, DB::table('users')->where('is_platform_admin', true)->count());
    }

    public function test_migration_usa_snapshot_proprio_sem_depender_da_matriz_da_aplicacao(): void
    {
        $codigo = file_get_contents(base_path(self::MIGRATION));

        $this->assertStringNotContainsString('AdminPermissionMatrix', $codigo);
        $this->assertDoesNotMatchRegularExpression('/^use\s+App\\\\/m', $codigo);

        $migration = require base_path(self::MIGRATION);
        $snapshot = (new ReflectionClassConstant($migration, 'ADMIN_PERMISSION_NAMES'))->getValue();

        $this->assertCount(26, $snapshot);
        $this->assertSame(self::SNAPSHOT_DA_MIGRATION, $snapshot);
    }

    private function assertPapelRecebeuSnapshot(Role $papel): void
    {
        foreach (self::SNAPSHOT_DA_MIGRATION as $nome) {
            $permissao = Permission::withoutGlobalScopes()
                ->where('tenant_id', $papel->tenant_id)
                ->where('name', $nome)
                ->firstOrFail();

            $this->assertTrue($papel->fresh()->hasPermission($permissao), "o admin precisa receber {$nome}");
        }
    }

    /**
     * Linhas do papel que apontam para permissão ou pivot de outro tenant.
     */
    private function linhasForaDoProprioTenant(Role $papel): int
    {
        return DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('role_permission.role_id', $papel->id)
            ->where(fn ($query) => $query
                ->where('role_permission.tenant_id', '!=', $papel->tenant_id)
                ->orWhere('permissions.tenant_id', '!=', $papel->tenant_id))
            ->count();
    }

    private function retratoDaMigration(Role ...$papeis): array
    {
        return [
            'pivots' => array_map(fn (Role $papel) => $this->permissoesDoPapel($papel), $papeis),
            'role_permission' => DB::table('role_permission')->count(),
            'permissions' => DB::table('permissions')->orderBy('id')->pluck('id')->all(),
        ];
    }
}
