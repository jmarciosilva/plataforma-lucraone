<?php

namespace Tests\Feature\Admin;

use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Branches\Application\BranchService;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Gestão de unidades / filiais no painel administrativo.
 *
 * Protege principalmente: multi-tenancy (uma loja não vê filiais da outra),
 * unicidade de matriz por empresa, controle de acesso por permissão e o fluxo
 * completo de CRUD via HTTP.
 */
#[Group('branch')]
#[Group('branch-admin')]
class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $empresa;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create(['name' => 'Papelaria do Bairro']);
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->tenant);

        $this->empresa = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'ACTIVE',
        ]);

        $this->admin = User::factory()->create(['name' => 'Ana Admin']);
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->tenant, $this->admin);
        $this->admin = $this->admin->fresh();
    }

    // ----------------------------------------------------------------
    // Index
    // ----------------------------------------------------------------

    public function test_admin_ve_lista_de_filiais(): void
    {
        Branch::factory()->forCompany($this->empresa)->create(['name' => 'Loja Centro']);

        $this->actingAs($this->admin)
            ->get(route('branches.index'))
            ->assertOk()
            ->assertSee('Loja Centro');
    }

    public function test_usuario_sem_permissao_recebe_403_no_index(): void
    {
        $visitante = User::factory()->forTenant($this->tenant)->create();

        $this->actingAs($visitante)
            ->get(route('branches.index'))
            ->assertForbidden();
    }

    public function test_filiais_de_outro_tenant_nao_aparecem_na_lista(): void
    {
        $outraTenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($outraTenant);
        $outraEmpresa = Company::factory()->create(['tenant_id' => $outraTenant->id]);
        Branch::factory()->forCompany($outraEmpresa)->create(['name' => 'Filial Secreta']);

        Branch::factory()->forCompany($this->empresa)->create(['name' => 'Minha Loja']);

        $this->actingAs($this->admin)
            ->get(route('branches.index'))
            ->assertOk()
            ->assertSee('Minha Loja')
            ->assertDontSee('Filial Secreta');
    }

    // ----------------------------------------------------------------
    // Cadastro (create/store)
    // ----------------------------------------------------------------

    public function test_admin_cadastra_filial_simples(): void
    {
        $resposta = $this->actingAs($this->admin)
            ->post(route('branches.store'), [
                'company_id' => $this->empresa->id,
                'name' => 'Loja Sul',
                'code' => 'SUL',
                'timezone' => 'America/Sao_Paulo',
            ]);

        $resposta->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('branches', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Loja Sul',
            'code' => 'SUL',
            'status' => 'ACTIVE',
            'is_headquarters' => false,
        ]);
    }

    public function test_admin_cadastra_matriz(): void
    {
        $resposta = $this->actingAs($this->admin)
            ->post(route('branches.store'), [
                'company_id' => $this->empresa->id,
                'name' => 'Loja Matriz',
                'is_headquarters' => '1',
            ]);

        $resposta->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('branches', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Loja Matriz',
            'is_headquarters' => true,
        ]);
    }

    public function test_nao_e_possivel_cadastrar_segunda_matriz(): void
    {
        Branch::factory()->forCompany($this->empresa)->headquarters()->create(['name' => 'Já existe Matriz']);

        $resposta = $this->actingAs($this->admin)
            ->post(route('branches.store'), [
                'company_id' => $this->empresa->id,
                'name' => 'Nova Tentativa Matriz',
                'is_headquarters' => '1',
            ]);

        $resposta->assertSessionHasErrors('is_headquarters');

        $this->assertDatabaseCount('branches', 1);
    }

    public function test_matriz_de_outro_tenant_nao_bloqueia_cadastro_de_matriz(): void
    {
        $outraTenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($outraTenant);
        $outraEmpresa = Company::factory()->create(['tenant_id' => $outraTenant->id]);
        Branch::factory()->forCompany($outraEmpresa)->headquarters()->create();

        $resposta = $this->actingAs($this->admin)
            ->post(route('branches.store'), [
                'company_id' => $this->empresa->id,
                'name' => 'Minha Matriz',
                'is_headquarters' => '1',
            ]);

        $resposta->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('branches', [
            'tenant_id' => $this->tenant->id,
            'is_headquarters' => true,
        ]);
    }

    public function test_empresa_de_outro_tenant_e_rejeitada_no_store(): void
    {
        $outraTenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($outraTenant);
        $outraEmpresa = Company::factory()->create(['tenant_id' => $outraTenant->id]);

        $this->actingAs($this->admin)
            ->post(route('branches.store'), [
                'company_id' => $outraEmpresa->id,
                'name' => 'IDOR Attempt',
            ])
            ->assertStatus(422);
    }

    public function test_usuario_sem_permissao_nao_pode_cadastrar(): void
    {
        $visitante = User::factory()->forTenant($this->tenant)->create();

        $this->actingAs($visitante)
            ->post(route('branches.store'), [
                'company_id' => $this->empresa->id,
                'name' => 'Tentativa',
            ])
            ->assertForbidden();
    }

    // ----------------------------------------------------------------
    // Show
    // ----------------------------------------------------------------

    public function test_admin_ve_detalhe_da_filial(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->create(['name' => 'Loja Centro']);

        $this->actingAs($this->admin)
            ->get(route('branches.show', $filial))
            ->assertOk()
            ->assertSee('Loja Centro');
    }

    public function test_filial_de_outro_tenant_retorna_404(): void
    {
        $outraTenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($outraTenant);
        $outraEmpresa = Company::factory()->create(['tenant_id' => $outraTenant->id]);
        $outraFilial = Branch::factory()->forCompany($outraEmpresa)->create();

        $this->actingAs($this->admin)
            ->get(route('branches.show', $outraFilial))
            ->assertNotFound();
    }

    // ----------------------------------------------------------------
    // Edição (edit/update)
    // ----------------------------------------------------------------

    public function test_admin_atualiza_filial(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->create(['name' => 'Nome Antigo']);

        $resposta = $this->actingAs($this->admin)
            ->put(route('branches.update', $filial), [
                'name' => 'Nome Novo',
                'timezone' => 'America/Sao_Paulo',
            ]);

        $resposta->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('branches', ['id' => $filial->id, 'name' => 'Nome Novo']);
    }

    public function test_promover_filial_a_matriz_funciona_quando_nao_ha_outra(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->create(['is_headquarters' => false]);

        $resposta = $this->actingAs($this->admin)
            ->put(route('branches.update', $filial), [
                'name' => $filial->name,
                'is_headquarters' => '1',
            ]);

        $resposta->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('branches', ['id' => $filial->id, 'is_headquarters' => true]);
    }

    public function test_promover_filial_quando_ja_existe_matriz_falha(): void
    {
        $matriz = Branch::factory()->forCompany($this->empresa)->headquarters()->create(['name' => 'Matriz']);
        $filial = Branch::factory()->forCompany($this->empresa)->create(['name' => 'Filial']);

        $resposta = $this->actingAs($this->admin)
            ->put(route('branches.update', $filial), [
                'name' => $filial->name,
                'is_headquarters' => '1',
            ]);

        $resposta->assertSessionHasErrors('is_headquarters');

        $this->assertDatabaseHas('branches', ['id' => $matriz->id, 'is_headquarters' => true]);
        $this->assertDatabaseHas('branches', ['id' => $filial->id, 'is_headquarters' => false]);
    }

    public function test_editar_a_propria_matriz_sem_mudar_hq_nao_gera_erro(): void
    {
        $matriz = Branch::factory()->forCompany($this->empresa)->headquarters()->create(['name' => 'Matriz']);

        $resposta = $this->actingAs($this->admin)
            ->put(route('branches.update', $matriz), [
                'name' => 'Matriz Renomeada',
                'is_headquarters' => '1',
            ]);

        $resposta->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('branches', ['id' => $matriz->id, 'name' => 'Matriz Renomeada', 'is_headquarters' => true]);
    }

    // ----------------------------------------------------------------
    // Toggle
    // ----------------------------------------------------------------

    public function test_admin_desativa_filial_ativa(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->active()->create();

        $this->actingAs($this->admin)
            ->post(route('branches.toggle', $filial))
            ->assertRedirect();

        $this->assertDatabaseHas('branches', ['id' => $filial->id, 'status' => 'INACTIVE']);
    }

    public function test_admin_reativa_filial_inativa(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->inactive()->create();

        $this->actingAs($this->admin)
            ->post(route('branches.toggle', $filial))
            ->assertRedirect();

        $this->assertDatabaseHas('branches', ['id' => $filial->id, 'status' => 'ACTIVE']);
    }

    public function test_toggle_de_filial_de_outro_tenant_retorna_404(): void
    {
        $outraTenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($outraTenant);
        $outraEmpresa = Company::factory()->create(['tenant_id' => $outraTenant->id]);
        $outraFilial = Branch::factory()->forCompany($outraEmpresa)->create();

        $this->actingAs($this->admin)
            ->post(route('branches.toggle', $outraFilial))
            ->assertNotFound();
    }

    // ----------------------------------------------------------------
    // AuditLog
    // ----------------------------------------------------------------

    public function test_criacao_de_filial_gera_auditoria(): void
    {
        $this->actingAs($this->admin)
            ->post(route('branches.store'), [
                'company_id' => $this->empresa->id,
                'name' => 'Loja Auditada',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'action' => 'branch.created',
            'entity_type' => 'branch',
        ]);
    }

    public function test_criacao_como_matriz_gera_auditoria_de_hq(): void
    {
        $this->actingAs($this->admin)
            ->post(route('branches.store'), [
                'company_id' => $this->empresa->id,
                'name' => 'Matriz',
                'is_headquarters' => '1',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'action' => 'branch.headquarters_assigned',
        ]);
    }

    public function test_toggle_para_inativo_gera_auditoria(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->active()->create();

        $this->actingAs($this->admin)
            ->post(route('branches.toggle', $filial));

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'action' => 'branch.deactivated',
            'entity_id' => $filial->id,
        ]);
    }

    // ----------------------------------------------------------------
    // Unicidade da matriz — dois POST concorrentes
    // ----------------------------------------------------------------

    public function test_dois_posts_concorrentes_de_matriz_so_criam_uma(): void
    {
        // Simula duas requisições HTTP sequenciais (não há paralelismo real
        // em teste, mas garante que o lock no service bloqueia a segunda).
        $resposta1 = $this->actingAs($this->admin)
            ->post(route('branches.store'), [
                'company_id' => $this->empresa->id,
                'name' => 'Primeira Matriz',
                'is_headquarters' => '1',
            ]);

        $resposta2 = $this->actingAs($this->admin)
            ->post(route('branches.store'), [
                'company_id' => $this->empresa->id,
                'name' => 'Segunda Matriz',
                'is_headquarters' => '1',
            ]);

        // A segunda resposta tem os erros de validação (sessão da última request)
        $resposta2->assertSessionHasErrors('is_headquarters');

        // Apenas uma matriz foi criada no banco
        $this->assertSame(1, Branch::where('tenant_id', $this->tenant->id)->where('is_headquarters', true)->count());
        $this->assertDatabaseHas('branches', ['name' => 'Primeira Matriz', 'is_headquarters' => true]);
        $this->assertDatabaseMissing('branches', ['name' => 'Segunda Matriz']);
    }
}
