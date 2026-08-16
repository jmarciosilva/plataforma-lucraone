<?php

namespace Tests\Feature\Admin;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3.3 — Admin Dashboard
 *
 * O painel deve mostrar dados reais do tenant em uso, sem depender de
 * placeholders e sem perder a proteção do TenantContext.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $outroTenant;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'name' => 'Casa Alta',
            'timezone' => 'America/Sao_Paulo',
        ]);

        $this->outroTenant = Tenant::factory()->create(['name' => 'Outra Loja']);

        $this->usuario = User::factory()
            ->forTenant($this->tenant)
            ->create([
                'name' => 'Maria Operadora',
                'last_login_at' => '2026-08-16 15:30:00',
            ]);
    }

    public function test_dashboard_carrega_com_breadcrumb_e_cabecalho(): void
    {
        $this->actingAs($this->usuario)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('aria-label="breadcrumb"', false)
            ->assertSee('dashboard')
            ->assertSee('Casa Alta')
            ->assertSee('visão geral do estabelecimento', false);
    }

    public function test_widgets_exibem_contagens_reais_do_tenant_atual(): void
    {
        User::factory(2)->forTenant($this->tenant)->create();
        User::factory(3)->forTenant($this->outroTenant)->create();

        $empresa = Company::factory()->forCurrentTenant($this->tenant->id)->create();
        Company::factory()->forCurrentTenant($this->outroTenant->id)->create();

        Product::factory(2)->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $empresa->id,
        ]);

        Product::factory(4)->create([
            'tenant_id' => $this->outroTenant->id,
            'company_id' => Company::factory()->forCurrentTenant($this->outroTenant->id)->create()->id,
        ]);

        $resposta = $this->actingAs($this->usuario)->get('/dashboard');

        $resposta->assertOk()
            ->assertSeeInOrder(['tenants', '1'])
            ->assertSeeInOrder(['usuários', '3'], false)
            ->assertSeeInOrder(['empresas', '1'])
            ->assertSeeInOrder(['produtos', '2']);
    }

    public function test_contagem_de_tenants_reflete_estabelecimentos_acessiveis_pela_pessoa(): void
    {
        $this->usuario->joinTenant($this->outroTenant->id);

        $this->actingAs($this->usuario)
            ->post(route('estabelecimentos.definir'), [
                'tenant_id' => $this->tenant->id,
            ])
            ->assertRedirect(route('dashboard'));

        $this
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeInOrder(['tenants', '2']);
    }

    public function test_ultimo_acesso_aparece_formatado_no_timezone_do_tenant(): void
    {
        $this->actingAs($this->usuario)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('último acesso', false)
            ->assertSee('16/08/2026 12:30');
    }

    public function test_menu_renderiza_itens_e_estado_ativo(): void
    {
        $this->actingAs($this->usuario)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('aria-current="page"', false)
            ->assertSee('tenants')
            ->assertSee('usuários', false)
            ->assertSee('empresas')
            ->assertSee('permissões', false)
            ->assertSee('produtos')
            ->assertSee('aria-disabled="true"', false);
    }

    public function test_layout_tem_controles_responsivos_mobile(): void
    {
        $this->actingAs($this->usuario)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('x-data="{ menuAberto: false }"', false)
            ->assertSee('aria-label="abrir menu"', false)
            ->assertSee('lg:hidden', false)
            ->assertSee('lg:flex', false);
    }
}
