<?php

namespace Tests\Feature\Admin;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3.1 — Frontend Setup & Layout
 *
 * Valida o esqueleto do painel: rotas, proteção de acesso, renderização
 * dos componentes Blade e presença dos tokens do design system.
 */
class LayoutTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['name' => 'Casa Alta']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Maria Operadora',
        ]);
    }

    public function test_raiz_redireciona_para_dashboard(): void
    {
        $this->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_visitante_e_redirecionado_para_login(): void
    {
        $this->get('/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_tela_de_login_renderiza_a_marca(): void
    {
        $resposta = $this->get('/login');

        $resposta->assertOk()
            ->assertSee('LUCRA', escape: false)
            ->assertSee('texto-sol', escape: false);
    }

    public function test_dashboard_renderiza_sidebar_e_componentes(): void
    {
        $resposta = $this->actingAs($this->user)->get('/dashboard');

        $resposta->assertOk()
            // Marca e tenant ativo na sidebar
            ->assertSee('LUCRA', escape: false)
            ->assertSee('Casa Alta')
            // Itens de menu decididos com os sócios
            ->assertSee('dashboard')
            ->assertSee('tenants')
            ->assertSee('usuários', escape: false)
            ->assertSee('empresas')
            ->assertSee('permissões', escape: false)
            ->assertSee('produtos')
            // Usuário logado e saída
            ->assertSee('Maria Operadora')
            ->assertSee('sair');
    }

    public function test_componentes_do_design_system_renderizam(): void
    {
        $resposta = $this->actingAs($this->user)->get('/dashboard');

        $resposta->assertOk()
            // Cartão, rótulo de seção e KPI
            ->assertSee('cartao', escape: false)
            ->assertSee('rotulo-secao', escape: false)
            ->assertSee('font-comanda', escape: false)
            // Item de menu ativo usa o gradiente da referência
            ->assertSee('gradiente-sol', escape: false);
    }

    public function test_assets_compilados_existem(): void
    {
        $manifesto = public_path('build/manifest.json');

        $this->assertFileExists(
            $manifesto,
            'Assets não compilados. Rode: npm run build'
        );

        $entradas = json_decode(file_get_contents($manifesto), true);

        $this->assertArrayHasKey('resources/css/app.css', $entradas);
        $this->assertArrayHasKey('resources/js/app.js', $entradas);
    }
}
