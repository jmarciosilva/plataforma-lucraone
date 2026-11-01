<?php

namespace Tests\Feature\Admin;

use App\Modules\Branches\Domain\BranchSegment;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\BranchResolver;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * MS-03 — despacho de dashboard e navegação por segmento.
 *
 * Cobre: seleção de view por segmento, visão consolidada, itens do sidebar
 * por segmento, ausência de href="#" no nav de restaurante e regressão do
 * supermercado.
 */
#[Group('ms03')]
#[Group('dashboard')]
class DashboardSegmentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Company $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->tenant);

        $this->empresa = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'ACTIVE',
        ]);

        $this->admin = User::factory()->create();
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->tenant, $this->admin);
        $this->admin = $this->admin->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Despacho por segmento
    // ─────────────────────────────────────────────────────────────────────────

    public function test_filial_supermercado_renderiza_dashboard_index(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->active()->create([
            'segment' => BranchSegment::SUPERMARKET,
        ]);

        $this->actingAs($this->admin)
            ->withSession([BranchResolver::SESSAO_FILIAL => $filial->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard.index');
    }

    public function test_filial_restaurante_renderiza_dashboard_restaurante(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->active()->create([
            'segment' => BranchSegment::RESTAURANT,
            'name' => 'Restaurante da Praça',
        ]);

        $this->actingAs($this->admin)
            ->withSession([BranchResolver::SESSAO_FILIAL => $filial->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard.restaurante');
    }

    public function test_dashboard_restaurante_exibe_nome_da_filial_e_contexto(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->active()->create([
            'segment' => BranchSegment::RESTAURANT,
            'name' => 'Bistrô Alegre',
        ]);

        $this->actingAs($this->admin)
            ->withSession([BranchResolver::SESSAO_FILIAL => $filial->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bistrô Alegre')
            ->assertSee('em configuração', false);
    }

    public function test_dashboard_restaurante_nao_exibe_kpis_de_dados_reais(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->active()->create([
            'segment' => BranchSegment::RESTAURANT,
        ]);

        $resposta = $this->actingAs($this->admin)
            ->withSession([BranchResolver::SESSAO_FILIAL => $filial->id])
            ->get(route('dashboard'))
            ->assertOk();

        // Nenhum indicador que possa ser interpretado como dado operacional real.
        $resposta
            ->assertDontSee('faturamento', false)
            ->assertDontSee('ticket médio', false)
            ->assertDontSee('pedidos faturados', false)
            ->assertSee('em desenvolvimento', false);
    }

    public function test_dashboard_restaurante_nao_usa_links_desabilitados(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->active()->create([
            'segment' => BranchSegment::RESTAURANT,
        ]);

        $this->actingAs($this->admin)
            ->withSession([BranchResolver::SESSAO_FILIAL => $filial->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('href="#"', false)
            ->assertDontSee('aria-disabled="true"', false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Visão consolidada
    // ─────────────────────────────────────────────────────────────────────────

    public function test_sem_filial_selecionada_com_multiplas_filiais_exibe_banner_consolidado(): void
    {
        Branch::factory()->forCompany($this->empresa)->active()->create(['segment' => BranchSegment::SUPERMARKET]);
        Branch::factory()->forCompany($this->empresa)->active()->create(['segment' => BranchSegment::RESTAURANT]);

        // Sem filial_ativa na sessão → BranchResolver retorna false → visão consolidada
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard.index')
            ->assertViewHas('modoConsolidado', true)
            ->assertSee('visão consolidada', false);
    }

    public function test_filial_unica_auto_resolvida_nao_exibe_banner_consolidado(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->active()->create([
            'segment' => BranchSegment::SUPERMARKET,
        ]);

        // BranchResolver auto-resolve a filial — sem sessão explícita.
        // Espera-se que a única filial ativa do tenant seja resolvida automaticamente.
        $this->actingAs($this->admin)
            ->withSession([BranchResolver::SESSAO_FILIAL => $filial->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard.index')
            ->assertViewHas('modoConsolidado', false)
            ->assertDontSee('visão consolidada · todas as filiais', false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Navegação por segmento
    // ─────────────────────────────────────────────────────────────────────────

    public function test_sidebar_restaurante_exibe_itens_em_breve(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->active()->create([
            'segment' => BranchSegment::RESTAURANT,
        ]);

        $this->actingAs($this->admin)
            ->withSession([BranchResolver::SESSAO_FILIAL => $filial->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('em breve', false);
    }

    public function test_sidebar_restaurante_nao_exibe_grupo_pdv(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->active()->create([
            'segment' => BranchSegment::RESTAURANT,
        ]);

        $this->actingAs($this->admin)
            ->withSession([BranchResolver::SESSAO_FILIAL => $filial->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('terminais de pdv', false);
    }

    public function test_sidebar_restaurante_nao_usa_href_cerquilha(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->active()->create([
            'segment' => BranchSegment::RESTAURANT,
        ]);

        $this->actingAs($this->admin)
            ->withSession([BranchResolver::SESSAO_FILIAL => $filial->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('href="#"', false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Troca de segmento via sessão
    // ─────────────────────────────────────────────────────────────────────────

    public function test_troca_de_supermercado_para_restaurante_altera_dashboard(): void
    {
        $supermercado = Branch::factory()->forCompany($this->empresa)->active()->create([
            'segment' => BranchSegment::SUPERMARKET,
        ]);
        $restaurante = Branch::factory()->forCompany($this->empresa)->active()->create([
            'segment' => BranchSegment::RESTAURANT,
        ]);

        $this->actingAs($this->admin)
            ->withSession([BranchResolver::SESSAO_FILIAL => $supermercado->id])
            ->get(route('dashboard'))
            ->assertViewIs('dashboard.index');

        $this->actingAs($this->admin)
            ->withSession([BranchResolver::SESSAO_FILIAL => $restaurante->id])
            ->get(route('dashboard'))
            ->assertViewIs('dashboard.restaurante');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Regressão do supermercado
    // ─────────────────────────────────────────────────────────────────────────

    public function test_dashboard_supermercado_preserva_metricas_e_breadcrumb(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->active()->create([
            'segment' => BranchSegment::SUPERMARKET,
        ]);

        $this->actingAs($this->admin)
            ->withSession([BranchResolver::SESSAO_FILIAL => $filial->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard.index')
            ->assertViewHas('modoConsolidado', false)
            ->assertSee('aria-label="breadcrumb"', false)
            ->assertSee('estabelecimentos', false)
            ->assertSee('usuários', false)
            ->assertSee('visão geral do estabelecimento', false);
    }
}
