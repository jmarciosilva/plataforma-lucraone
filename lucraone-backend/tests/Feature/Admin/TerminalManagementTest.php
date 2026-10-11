<?php

namespace Tests\Feature\Admin;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Gestão de terminais PDV no painel administrativo.
 *
 * Cobre: multi-tenant, controle de acesso, cota por filial, máquina de estado
 * (PENDING → ACTIVE/BLOCKED/REVOKED) e emissão de código de pareamento.
 */
#[Group('terminal')]
#[Group('terminal-admin')]
class TerminalManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Company $empresa;

    private Branch $filial;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create(['name' => 'Rede Mercado Ltda']);
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->tenant);

        $this->empresa = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'ACTIVE',
        ]);

        $this->filial = Branch::factory()->forCompany($this->empresa)->create([
            'name' => 'Loja Principal',
            'max_pdv_terminals' => 3,
        ]);

        $this->admin = User::factory()->create(['name' => 'Ana Admin']);
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->tenant, $this->admin);
        $this->admin = $this->admin->fresh();
    }

    // ----------------------------------------------------------------
    // Index
    // ----------------------------------------------------------------

    public function test_admin_ve_lista_de_terminais(): void
    {
        Terminal::factory()->forBranch($this->filial)->create(['name' => 'Caixa 01']);

        $this->actingAs($this->admin)
            ->get(route('terminals.index'))
            ->assertOk()
            ->assertSee('Caixa 01');
    }

    public function test_usuario_sem_permissao_recebe_403_no_index(): void
    {
        $visitante = User::factory()->forTenant($this->tenant)->create();

        $this->actingAs($visitante)
            ->get(route('terminals.index'))
            ->assertForbidden();
    }

    public function test_terminais_de_outro_tenant_nao_aparecem(): void
    {
        $outraTenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($outraTenant);
        $outraEmpresa = Company::factory()->create(['tenant_id' => $outraTenant->id]);
        $outraFilial = Branch::factory()->forCompany($outraEmpresa)->create();
        Terminal::factory()->forBranch($outraFilial)->create(['name' => 'Caixa Secreto']);

        $this->actingAs($this->admin)
            ->get(route('terminals.index'))
            ->assertOk()
            ->assertDontSee('Caixa Secreto');
    }

    // ----------------------------------------------------------------
    // Create / Store
    // ----------------------------------------------------------------

    public function test_admin_acessa_formulario_de_criacao_de_terminal(): void
    {
        $this->actingAs($this->admin)
            ->get(route('terminals.create'))
            ->assertOk()
            ->assertViewIs('terminals.create')
            ->assertViewHas('filiais');
    }

    public function test_formulario_renderiza_sem_terminais_cadastrados(): void
    {
        $this->assertDatabaseMissing('terminals', ['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin)
            ->get(route('terminals.create'))
            ->assertOk();
    }

    public function test_usuario_sem_permissao_recebe_403_no_create(): void
    {
        $visitante = User::factory()->forTenant($this->tenant)->create();

        $this->actingAs($visitante)
            ->get(route('terminals.create'))
            ->assertForbidden();
    }

    public function test_admin_de_outro_tenant_nao_ve_filiais_alheias(): void
    {
        $outraTenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($outraTenant);
        $outraEmpresa = Company::factory()->create(['tenant_id' => $outraTenant->id]);
        $outraFilial = Branch::factory()->forCompany($outraEmpresa)->create(['name' => 'Filial Secreta']);

        $this->actingAs($this->admin)
            ->get(route('terminals.create'))
            ->assertOk()
            ->assertDontSee('Filial Secreta');
    }

    public function test_formulario_nao_emite_credenciais_de_maquina(): void
    {
        $this->actingAs($this->admin)
            ->get(route('terminals.create'))
            ->assertOk()
            ->assertDontSee('installation_id');
    }

    public function test_admin_cria_terminal(): void
    {
        $this->actingAs($this->admin)
            ->post(route('terminals.store'), [
                'branch_id' => $this->filial->id,
                'name' => 'Caixa Recepção',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('terminals', [
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->filial->id,
            'name' => 'Caixa Recepção',
            'status' => Terminal::STATUS_PENDING,
        ]);
    }

    public function test_terminal_criado_fica_em_pending(): void
    {
        $this->actingAs($this->admin)
            ->post(route('terminals.store'), [
                'branch_id' => $this->filial->id,
                'name' => 'Caixa 02',
            ]);

        $terminal = Terminal::where('name', 'Caixa 02')->first();
        $this->assertNotNull($terminal);
        $this->assertSame(Terminal::STATUS_PENDING, $terminal->status);
    }

    public function test_store_recusa_branch_de_outro_tenant(): void
    {
        $outraTenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($outraTenant);
        $outraEmpresa = Company::factory()->create(['tenant_id' => $outraTenant->id]);
        $outraFilial = Branch::factory()->forCompany($outraEmpresa)->create();

        $this->actingAs($this->admin)
            ->post(route('terminals.store'), [
                'branch_id' => $outraFilial->id,
                'name' => 'Caixa Intruso',
            ])
            ->assertSessionHasErrors(['branch_id']);

        $this->assertDatabaseMissing('terminals', ['name' => 'Caixa Intruso']);
    }

    public function test_store_recusa_quando_cota_esgotada(): void
    {
        // Filial tem max_pdv_terminals = 3; criar 3 terminais
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_ACTIVE, 'installation_id' => '550e8400-e29b-41d4-a716-446655440001']);
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_ACTIVE, 'installation_id' => '550e8400-e29b-41d4-a716-446655440002']);
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_BLOCKED, 'installation_id' => '550e8400-e29b-41d4-a716-446655440003']);

        $this->actingAs($this->admin)
            ->post(route('terminals.store'), [
                'branch_id' => $this->filial->id,
                'name' => 'Caixa Excedente',
            ])
            ->assertSessionHasErrors(['branch_id']);

        $this->assertDatabaseMissing('terminals', ['name' => 'Caixa Excedente']);
    }

    public function test_terminais_revogados_nao_contam_para_a_cota(): void
    {
        // 2 ativos + 1 revogado = 2 contam; limite é 3, então ainda cabe 1
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_ACTIVE, 'installation_id' => '550e8400-e29b-41d4-a716-446655440011']);
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_ACTIVE, 'installation_id' => '550e8400-e29b-41d4-a716-446655440012']);
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_REVOKED]);

        $this->actingAs($this->admin)
            ->post(route('terminals.store'), [
                'branch_id' => $this->filial->id,
                'name' => 'Caixa Novo',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('terminals', ['name' => 'Caixa Novo', 'status' => Terminal::STATUS_PENDING]);
    }

    // ----------------------------------------------------------------
    // Show
    // ----------------------------------------------------------------

    public function test_admin_ve_detalhe_do_terminal(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create(['name' => 'Caixa Detalhe']);

        $this->actingAs($this->admin)
            ->get(route('terminals.show', $terminal))
            ->assertOk()
            ->assertSee('Caixa Detalhe');
    }

    public function test_terminal_de_outro_tenant_retorna_404(): void
    {
        $outraTenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($outraTenant);
        $outraEmpresa = Company::factory()->create(['tenant_id' => $outraTenant->id]);
        $outraFilial = Branch::factory()->forCompany($outraEmpresa)->create();
        $terminal = Terminal::factory()->forBranch($outraFilial)->create();

        $this->actingAs($this->admin)
            ->get(route('terminals.show', $terminal))
            ->assertNotFound();
    }

    // ----------------------------------------------------------------
    // Update
    // ----------------------------------------------------------------

    public function test_admin_atualiza_nome_do_terminal(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create(['name' => 'Caixa Antigo']);

        $this->actingAs($this->admin)
            ->put(route('terminals.update', $terminal), ['name' => 'Caixa Novo'])
            ->assertRedirect(route('terminals.show', $terminal));

        $this->assertDatabaseHas('terminals', ['id' => $terminal->id, 'name' => 'Caixa Novo']);
    }

    // ----------------------------------------------------------------
    // Block / Unblock / Revoke — state machine
    // ----------------------------------------------------------------

    public function test_admin_bloqueia_terminal_ativo(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_ACTIVE,
            'installation_id' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $this->actingAs($this->admin)
            ->post(route('terminals.block', $terminal))
            ->assertRedirect();

        $this->assertSame(Terminal::STATUS_BLOCKED, $terminal->fresh()->status);
    }

    public function test_nao_pode_bloquear_terminal_pending(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin)
            ->post(route('terminals.block', $terminal))
            ->assertSessionHasErrors();
    }

    public function test_admin_desbloqueia_terminal_bloqueado(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_BLOCKED,
            'installation_id' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $this->actingAs($this->admin)
            ->post(route('terminals.unblock', $terminal))
            ->assertRedirect();

        $this->assertSame(Terminal::STATUS_ACTIVE, $terminal->fresh()->status);
    }

    public function test_admin_revoga_terminal_pending(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin)
            ->post(route('terminals.revoke', $terminal))
            ->assertRedirect();

        $this->assertSame(Terminal::STATUS_REVOKED, $terminal->fresh()->status);
    }

    public function test_admin_revoga_terminal_ativo(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_ACTIVE,
            'installation_id' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $this->actingAs($this->admin)
            ->post(route('terminals.revoke', $terminal))
            ->assertRedirect();

        $this->assertSame(Terminal::STATUS_REVOKED, $terminal->fresh()->status);
    }

    public function test_nao_pode_revogar_terminal_ja_revogado(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_REVOKED,
        ]);

        $this->actingAs($this->admin)
            ->post(route('terminals.revoke', $terminal))
            ->assertSessionHasErrors();
    }

    // ----------------------------------------------------------------
    // Pairing code
    // ----------------------------------------------------------------

    public function test_admin_emite_codigo_de_pareamento_para_terminal_pending(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin)
            ->post(route('terminals.pairing-code', $terminal))
            ->assertRedirect()
            ->assertSessionHas('codigoPareamento');
    }

    public function test_codigo_expira_na_sessao_e_string_e_nao_objeto(): void
    {
        // Regressão: cookie session driver serializa CarbonImmutable como string;
        // o controller deve pré-formatar para evitar ->format() em string na view.
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('terminals.pairing-code', $terminal));

        $expira = $response->getSession()->get('codigoExpira');
        $this->assertIsString($expira, 'codigoExpira deve ser string pré-formatada');
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2} de \d{2}\/\d{2}\/\d{4}$/', $expira);
    }

    public function test_pagina_show_renderiza_sem_500_apos_emissao_de_codigo(): void
    {
        // Regressão: HTTP 500 no redirect de volta para terminals.show após POST.
        // O modal chamava ->format() numa string; deve renderizar sem exceção.
        // Usa from() para simular o Referer e fazer back() redirecionar corretamente.
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin)
            ->from(route('terminals.show', $terminal))
            ->followingRedirects()
            ->post(route('terminals.pairing-code', $terminal))
            ->assertOk()
            ->assertSee('Informe este código ao operador de caixa');
    }

    public function test_show_nao_exibe_codigo_em_get_comum_apos_flash_consumido(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_PENDING,
        ]);

        // GET direto sem flash não deve mostrar o modal (sem "Informe este código")
        $this->actingAs($this->admin)
            ->get(route('terminals.show', $terminal))
            ->assertOk()
            ->assertDontSee('Informe este código ao operador de caixa');
    }

    public function test_usuario_sem_permissao_recebe_403_no_pairing_code(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_PENDING,
        ]);
        $visitante = User::factory()->forTenant($this->tenant)->create();

        $this->actingAs($visitante)
            ->post(route('terminals.pairing-code', $terminal))
            ->assertForbidden();
    }

    public function test_terminal_bloqueado_nao_aceita_emissao_de_codigo(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_BLOCKED,
        ]);

        $this->actingAs($this->admin)
            ->post(route('terminals.pairing-code', $terminal))
            ->assertRedirect()
            ->assertSessionHasErrors();
    }

    public function test_terminal_revogado_nao_aceita_emissao_de_codigo(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_REVOKED,
        ]);

        $this->actingAs($this->admin)
            ->post(route('terminals.pairing-code', $terminal))
            ->assertRedirect()
            ->assertSessionHasErrors();
    }

    // ----------------------------------------------------------------
    // Branches/show — seção de terminais
    // ----------------------------------------------------------------

    public function test_branches_show_lista_terminais_da_filial(): void
    {
        Terminal::factory()->forBranch($this->filial)->create(['name' => 'Caixa na Filial']);

        $this->actingAs($this->admin)
            ->get(route('branches.show', $this->filial))
            ->assertOk()
            ->assertSee('Caixa na Filial');
    }
}
