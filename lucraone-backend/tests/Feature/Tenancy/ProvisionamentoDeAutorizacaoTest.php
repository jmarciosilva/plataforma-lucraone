<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Authorization\Domain\AdminPermissionMatrix;
use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Feature\Security\Concerns\MontaCenariosDeAutorizacao;
use Tests\TestCase;

/**
 * ONB-01A · E e F — fonte única da autorização padrão.
 *
 * Havia dois conceitos divergentes de "autorização padrão" para a mesma
 * intenção: o AuthorizationSeeder provisionava 4 papéis e 28 permissões,
 * enquanto a criação pelo painel provisionava 1 papel e 26 permissões. Um
 * estabelecimento criado pela tela nascia sem manager, user e viewer — e, como
 * não existe CRUD de papéis, sem como criá-los depois.
 *
 * A matriz esperada é caracterizada aqui à mão, de propósito: se o teste lesse
 * a mesma constante que a implementação usa, provaria apenas que a classe é
 * igual a si mesma.
 *
 * As duas permissões órfãs (`manage-assigned-branches` e
 * `view-assigned-branches`) continuam no catálogo e continuam sem papel, por
 * decisão do SEC-04. Fechar os 28 atribuindo-as seria uma regressão de
 * segurança disfarçada de arredondamento.
 */
#[Group('onb-01a')]
class ProvisionamentoDeAutorizacaoTest extends TestCase
{
    use MontaCenariosDeAutorizacao, RefreshDatabase;

    /**
     * Catálogo de permissões de um estabelecimento.
     */
    private const CATALOGO = [
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
        'manage-assigned-branches', 'view-assigned-branches',
    ];

    private const MANAGER = [
        'manage-companies', 'view-companies',
        'manage-products', 'view-products',
        'manage-inventory', 'view-inventory',
        'manage-sales', 'view-sales',
        'manage-customers', 'view-customers',
        'view-reports',
        'view-automations',
        'manage-users', 'view-users',
        'view-branches',
    ];

    private const USUARIO = [
        'view-companies',
        'view-products',
        'view-inventory',
        'view-sales',
        'view-customers',
        'view-reports',
        'view-automations',
        'view-users',
    ];

    private const VIEWER = [
        'view-companies',
        'view-products',
        'view-inventory',
        'view-sales',
        'view-customers',
        'view-reports',
        'view-automations',
        'view-branches',
    ];

    private const ORFAS = ['manage-assigned-branches', 'view-assigned-branches'];

    private Tenant $tenantBase;

    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantBase = Tenant::factory()->active()->create(['name' => 'Base da Plataforma']);
        $this->provisionarPapeisPadrao();

        // Platform Admin precisa de vínculo ativo para o auth.web resolver um
        // estabelecimento; a autoridade sobre /tenants vem do marcador, não de papel.
        $this->platformAdmin = User::factory()
            ->platformAdmin()
            ->forTenant($this->tenantBase)
            ->create(['email' => 'plataforma@lucraone.test']);
    }

    private function criarTenantPeloPainel(string $nome = 'Cliente Novo'): Tenant
    {
        // Criar um estabelecimento vincula quem criou. A partir do segundo
        // vínculo o AutenticarWeb passa a exigir escolha explícita, então o
        // operador define o estabelecimento em uso antes de criar o próximo —
        // é o que ele faz na tela. Sem isto o POST seguinte seria interceptado
        // pelo seletor de estabelecimento e nunca chegaria ao controller.
        $this->actingAs($this->platformAdmin)
            ->post(route('estabelecimentos.definir'), ['tenant_id' => $this->tenantBase->id])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->platformAdmin)
            ->post(route('tenants.store'), [
                'name' => $nome,
                'status' => 'ACTIVE',
                'plan' => 'free',
                'timezone' => 'America/Sao_Paulo',
                'locale' => 'pt-BR',
                'currency' => 'BRL',
            ])
            ->assertRedirect();

        $tenant = Tenant::where('name', $nome)->firstOrFail();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);

        return $tenant;
    }

    /**
     * @return list<string>
     */
    private function permissoesDoPapelNoTenant(Tenant $tenant, string $papel): array
    {
        $role = Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', $papel)
            ->firstOrFail();

        $nomes = DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('role_permission.role_id', $role->id)
            ->pluck('permissions.name')
            ->all();

        sort($nomes);

        return array_values($nomes);
    }

    /**
     * @return array<string, list<string>>
     */
    private function matrizDoTenant(Tenant $tenant): array
    {
        $matriz = [];

        foreach (Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('name')->get() as $role) {
            $matriz[$role->name] = $this->permissoesDoPapelNoTenant($tenant, $role->name);
        }

        return $matriz;
    }

    private function ordenado(array $lista): array
    {
        sort($lista);

        return array_values($lista);
    }

    public function test_tenant_criado_pelo_painel_recebe_os_quatro_papeis_padrao(): void
    {
        $tenant = $this->criarTenantPeloPainel();

        $papeis = Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $this->assertSame(['admin', 'manager', 'user', 'viewer'], $papeis);
    }

    public function test_tenant_criado_pelo_painel_recebe_o_catalogo_completo_de_permissoes(): void
    {
        $tenant = $this->criarTenantPeloPainel();

        $permissoes = Permission::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->pluck('name')
            ->all();

        $this->assertCount(28, $permissoes);
        $this->assertSame($this->ordenado(self::CATALOGO), $this->ordenado($permissoes));
    }

    public function test_admin_do_tenant_novo_recebe_exatamente_a_matriz_do_admin_permission_matrix(): void
    {
        $tenant = $this->criarTenantPeloPainel();

        $this->assertSame(
            $this->ordenado(AdminPermissionMatrix::NAMES),
            $this->permissoesDoPapelNoTenant($tenant, 'admin')
        );
    }

    public function test_admin_do_tenant_novo_tem_vinte_e_seis_permissoes(): void
    {
        $tenant = $this->criarTenantPeloPainel();

        $this->assertCount(26, $this->permissoesDoPapelNoTenant($tenant, 'admin'));
    }

    public function test_manager_do_tenant_novo_mantem_a_matriz_esperada(): void
    {
        $tenant = $this->criarTenantPeloPainel();

        $permissoes = $this->permissoesDoPapelNoTenant($tenant, 'manager');

        $this->assertCount(15, $permissoes);
        $this->assertSame($this->ordenado(self::MANAGER), $permissoes);
    }

    public function test_user_do_tenant_novo_mantem_a_matriz_esperada(): void
    {
        $tenant = $this->criarTenantPeloPainel();

        $permissoes = $this->permissoesDoPapelNoTenant($tenant, 'user');

        $this->assertCount(8, $permissoes);
        $this->assertSame($this->ordenado(self::USUARIO), $permissoes);
    }

    public function test_viewer_do_tenant_novo_mantem_a_matriz_esperada(): void
    {
        $tenant = $this->criarTenantPeloPainel();

        $permissoes = $this->permissoesDoPapelNoTenant($tenant, 'viewer');

        $this->assertCount(8, $permissoes);
        $this->assertSame($this->ordenado(self::VIEWER), $permissoes);
    }

    public function test_as_duas_permissoes_orfas_continuam_no_catalogo(): void
    {
        $tenant = $this->criarTenantPeloPainel();

        foreach (self::ORFAS as $orfa) {
            $this->assertDatabaseHas('permissions', [
                'tenant_id' => $tenant->id,
                'name' => $orfa,
            ]);
        }
    }

    public function test_as_permissoes_orfas_nao_sao_atribuidas_a_nenhum_papel_padrao(): void
    {
        $tenant = $this->criarTenantPeloPainel();

        foreach ($this->matrizDoTenant($tenant) as $papel => $permissoes) {
            foreach (self::ORFAS as $orfa) {
                $this->assertNotContains(
                    $orfa,
                    $permissoes,
                    "o papel {$papel} não deve receber {$orfa}"
                );
            }
        }
    }

    public function test_provisionamento_e_idempotente(): void
    {
        $tenant = $this->criarTenantPeloPainel();

        $antes = $this->matrizDoTenant($tenant);

        app(ProvisionarEstabelecimento::class)->provisionarMatriz($tenant);
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($tenant);

        $this->assertSame($antes, $this->matrizDoTenant($tenant));
    }

    public function test_executar_duas_vezes_nao_duplica_papeis_permissoes_nem_vinculos(): void
    {
        $tenant = $this->criarTenantPeloPainel();

        $contagem = fn () => [
            'roles' => Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            'permissions' => Permission::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            'role_permission' => DB::table('role_permission')->where('tenant_id', $tenant->id)->count(),
        ];

        $antes = $contagem();

        $this->assertSame(4, $antes['roles']);
        $this->assertSame(28, $antes['permissions']);
        $this->assertSame(57, $antes['role_permission']);

        app(ProvisionarEstabelecimento::class)->provisionarMatriz($tenant);

        $this->assertSame($antes, $contagem());
    }

    public function test_um_tenant_nao_recebe_linhas_de_outro(): void
    {
        $tenantA = $this->criarTenantPeloPainel('Cliente A');
        $tenantB = $this->criarTenantPeloPainel('Cliente B');

        $this->assertNotSame($tenantA->id, $tenantB->id);

        $papeisDeA = Role::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->pluck('id');
        $papeisDeB = Role::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->pluck('id');

        $this->assertCount(4, $papeisDeA);
        $this->assertCount(4, $papeisDeB);
        $this->assertEmpty(array_intersect($papeisDeA->all(), $papeisDeB->all()));

        // Nenhuma linha de role_permission cruza estabelecimentos.
        $cruzadas = DB::table('role_permission')
            ->join('roles', 'roles.id', '=', 'role_permission.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->whereColumn('roles.tenant_id', '!=', 'permissions.tenant_id')
            ->count();

        $this->assertSame(0, $cruzadas);

        $this->assertSame($this->matrizDoTenant($tenantA), $this->matrizDoTenant($tenantB));
    }

    public function test_todos_os_papeis_e_permissoes_nascem_com_o_tenant_id_correto(): void
    {
        $tenant = $this->criarTenantPeloPainel();

        $this->assertSame(
            0,
            Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('tenant_id')->count()
        );

        foreach (Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get() as $role) {
            $this->assertSame($tenant->id, $role->tenant_id);
        }

        foreach (Permission::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get() as $permission) {
            $this->assertSame($tenant->id, $permission->tenant_id);
        }

        $linhasDoTenant = DB::table('role_permission')->where('tenant_id', $tenant->id)->count();
        $this->assertSame(57, $linhasDoTenant);
    }

    public function test_criacao_do_tenant_continua_transacional_no_caminho_feliz(): void
    {
        $tenant = $this->criarTenantPeloPainel();

        // Tenant, matriz, vínculo e papel do criador existem juntos.
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'ACTIVE']);
        $this->assertSame(4, Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $this->platformAdmin->id,
            'status' => TenantUser::STATUS_ACTIVE,
        ]);
        $this->assertTrue($this->platformAdmin->fresh()->hasRole('admin', $tenant->id));
    }

    public function test_falha_no_provisionamento_desfaz_a_criacao_do_tenant(): void
    {
        $this->mock(ProvisionarEstabelecimento::class, function ($mock) {
            $mock->shouldReceive('provisionarMatriz')
                ->andThrow(new RuntimeException('falha simulada no provisionamento'));
        });

        $tenantsAntes = Tenant::withTrashed()->count();

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->platformAdmin)
                ->post(route('tenants.store'), [
                    'name' => 'Cliente Que Nao Deve Existir',
                    'status' => 'ACTIVE',
                    'plan' => 'free',
                    'timezone' => 'America/Sao_Paulo',
                    'locale' => 'pt-BR',
                    'currency' => 'BRL',
                ]);

            $this->fail('a falha do provisionamento deveria propagar');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('falha simulada', $e->getMessage());
        }

        $this->assertSame($tenantsAntes, Tenant::withTrashed()->count());
        $this->assertDatabaseMissing('tenants', ['name' => 'Cliente Que Nao Deve Existir']);
        $this->assertDatabaseMissing('tenants', ['slug' => 'cliente-que-nao-deve-existir']);
    }

    public function test_quem_cria_o_tenant_continua_recebendo_vinculo_ativo_e_papel_admin(): void
    {
        $tenant = $this->criarTenantPeloPainel();

        $vinculo = $this->platformAdmin->fresh()->membershipFor($tenant->id);

        $this->assertNotNull($vinculo);
        $this->assertSame(TenantUser::STATUS_ACTIVE, $vinculo->status);
        $this->assertTrue($this->platformAdmin->fresh()->canAccessTenant($tenant->id));
        $this->assertTrue($this->platformAdmin->fresh()->hasRole('admin', $tenant->id));
    }

    /**
     * Caracterização de um efeito colateral preexistente, não introduzido aqui:
     * quem cria um estabelecimento ganha vínculo nele, e a partir do segundo
     * vínculo o painel passa a exigir a escolha do estabelecimento em uso.
     *
     * O ONB-01B decide explicitamente se o Platform Admin mantém esse acesso
     * (passo 3 do wizard). Até lá, o comportamento fica registrado aqui.
     */
    public function test_criar_estabelecimento_vincula_quem_criou_e_passa_a_exigir_escolha(): void
    {
        $this->assertCount(1, $this->platformAdmin->estabelecimentosDisponiveis());

        $novo = $this->criarTenantPeloPainel('Cliente Com Vinculo');

        $this->assertCount(2, $this->platformAdmin->fresh()->estabelecimentosDisponiveis());

        // Com dois vínculos e nenhuma escolha na sessão, o painel pede a escolha.
        $this->flushSession();

        $this->actingAs($this->platformAdmin)
            ->get(route('dashboard'))
            ->assertRedirect(route('estabelecimentos.escolher'));

        $this->assertTrue($this->platformAdmin->fresh()->canAccessTenant($novo->id));
    }

    public function test_admin_de_estabelecimento_continua_sem_criar_tenant(): void
    {
        $adminDoTenant = $this->membroComPapel($this->tenantBase, 'admin', [
            'email' => 'admin@base.test',
        ]);

        $this->actingAs($adminDoTenant)
            ->post(route('tenants.store'), [
                'name' => 'Tenant Indevido',
                'status' => 'ACTIVE',
                'plan' => 'free',
                'timezone' => 'America/Sao_Paulo',
                'locale' => 'pt-BR',
                'currency' => 'BRL',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tenants', ['name' => 'Tenant Indevido']);
    }

    public function test_platform_admin_continua_autorizado_pela_tenant_policy(): void
    {
        $this->assertTrue($this->platformAdmin->isPlatformAdmin());

        $this->actingAs($this->platformAdmin)
            ->get(route('tenants.create'))
            ->assertOk();

        $this->criarTenantPeloPainel('Cliente Autorizado');

        $this->assertDatabaseHas('tenants', ['name' => 'Cliente Autorizado']);
    }

    public function test_seeder_e_criacao_pelo_painel_produzem_a_mesma_matriz(): void
    {
        $peloPainel = $this->criarTenantPeloPainel('Cliente Do Painel');

        $peloSeeder = Tenant::factory()->active()->create(['name' => 'Cliente Do Seeder']);
        $this->seed(AuthorizationSeeder::class);

        $this->assertSame(
            $this->matrizDoTenant($peloPainel),
            $this->matrizDoTenant($peloSeeder),
            'o seeder e o painel não podem representar dois conceitos de autorização padrão'
        );

        $this->assertSame(
            ['admin', 'manager', 'user', 'viewer'],
            array_keys($this->matrizDoTenant($peloSeeder))
        );
    }

    public function test_seeder_rodando_sobre_tenant_ja_provisionado_nao_altera_nada(): void
    {
        $tenant = $this->criarTenantPeloPainel();

        $antes = $this->matrizDoTenant($tenant);

        $this->seed(AuthorizationSeeder::class);

        $this->assertSame($antes, $this->matrizDoTenant($tenant));
    }
}
