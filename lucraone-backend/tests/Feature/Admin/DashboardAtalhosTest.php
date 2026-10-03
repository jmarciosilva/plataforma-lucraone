<?php

namespace Tests\Feature\Admin;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Concerns\MontaCenariosDeAutorizacao;
use Tests\TestCase;

/**
 * ONB-01A · C e D — atalhos e nomenclatura do dashboard.
 *
 * O bloco de atalhos referenciava os nomes `tenants`, `usuarios` e `empresas`,
 * que nunca existiram como nomes de rota — as rotas reais são `tenants.index`,
 * `users.index` e `companies.index`. Como o template decidia o destino por
 * `Route::has()`, os três caíam em `href="#"`, desabilitados, anunciando
 * "cadastro chega no F3.4/F3.5/F3.6" para telas que a F3.4, a F3.5 e a F3.6
 * já entregaram.
 *
 * O teste que faltava é o último desta classe: ele confere que todo nome de
 * rota citado pelo bloco de atalhos existe de fato.
 */
#[Group('onb-01a')]
class DashboardAtalhosTest extends TestCase
{
    use MontaCenariosDeAutorizacao, RefreshDatabase;

    private Tenant $tenant;

    private User $adminDoEstabelecimento;

    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create(['name' => 'Casa Alta']);
        $this->provisionarPapeisPadrao();

        $this->adminDoEstabelecimento = $this->membroComPapel($this->tenant, 'admin', [
            'email' => 'admin@casa.test',
        ]);

        $this->platformAdmin = User::factory()
            ->platformAdmin()
            ->forTenant($this->tenant)
            ->create(['email' => 'plataforma@lucraone.test']);

        $this->platformAdmin->assignRole($this->papel($this->tenant, 'admin'), $this->tenant->id);
    }

    public function test_dashboard_continua_renderizando_para_usuario_autorizado(): void
    {
        $this->actingAs($this->adminDoEstabelecimento)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Casa Alta');
    }

    public function test_atalho_de_usuarios_aponta_para_a_rota_real(): void
    {
        $this->assertTrue(Route::has('users.index'));

        $this->actingAs($this->adminDoEstabelecimento)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('users.index'), false);
    }

    public function test_atalho_de_empresas_aponta_para_a_rota_real(): void
    {
        $this->assertTrue(Route::has('companies.index'));

        $this->actingAs($this->adminDoEstabelecimento)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('companies.index'), false);
    }

    public function test_a_rota_de_estabelecimentos_da_plataforma_existe(): void
    {
        $this->assertTrue(Route::has('tenants.index'));
    }

    public function test_nenhum_texto_de_etapa_futura_sobra_no_dashboard(): void
    {
        $resposta = $this->actingAs($this->adminDoEstabelecimento)
            ->get(route('dashboard'))
            ->assertOk();

        $resposta->assertDontSee('chega no F3.4')
            ->assertDontSee('chega no F3.5')
            ->assertDontSee('chega no F3.6')
            ->assertDontSee('chega no F3');
    }

    public function test_nenhum_atalho_desabilitado_representa_funcionalidade_entregue(): void
    {
        $this->actingAs($this->adminDoEstabelecimento)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('aria-disabled="true"', false)
            ->assertDontSee('href="#"', false)
            // As duas combinações que marcavam destino inexistente: a do bloco
            // de atalhos e a do item de menu. O `disabled:cursor-not-allowed`
            // do componente de botão é outra coisa — é a regra de estilo para
            // um botão de fato desabilitado, e continua legítima.
            ->assertDontSee('cursor-not-allowed opacity-60', false)
            ->assertDontSee('cursor-not-allowed opacity-50', false);
    }

    public function test_admin_de_estabelecimento_nao_recebe_atalho_de_plataforma(): void
    {
        $resposta = $this->actingAs($this->adminDoEstabelecimento)
            ->get(route('dashboard'))
            ->assertOk();

        $resposta->assertDontSee(route('tenants.index'), false)
            ->assertDontSee('clientes do LucraOne', false);
    }

    public function test_admin_de_estabelecimento_continua_barrado_na_area_de_plataforma(): void
    {
        $this->actingAs($this->adminDoEstabelecimento)
            ->get(route('tenants.index'))
            ->assertForbidden();
    }

    public function test_platform_admin_recebe_o_atalho_de_clientes_do_lucraone(): void
    {
        $this->actingAs($this->platformAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('clientes do LucraOne', false)
            ->assertSee(route('tenants.index'), false);
    }

    public function test_platform_admin_acessa_a_area_de_clientes_do_lucraone(): void
    {
        $this->actingAs($this->platformAdmin)
            ->get(route('tenants.index'))
            ->assertOk();
    }

    public function test_o_dashboard_nao_expoe_o_jargao_tenant_para_quem_opera(): void
    {
        $resposta = $this->actingAs($this->adminDoEstabelecimento)
            ->get(route('dashboard'))
            ->assertOk();

        // O indicador de estabelecimentos acessíveis não deve se chamar "tenants".
        $resposta->assertDontSee('>tenants<', false);
        $resposta->assertSee('estabelecimentos', false);
    }

    /**
     * O teste que faltava: todo nome de rota citado pelo bloco de atalhos
     * precisa existir. É exatamente a verificação que deixaria o defeito
     * original visível no primeiro `php artisan test`.
     */
    public function test_todos_os_nomes_de_rota_citados_pelo_dashboard_existem(): void
    {
        $blade = file_get_contents(resource_path('views/dashboard/index.blade.php'));

        $this->assertNotFalse($blade, 'a view do dashboard precisa ser legível');

        preg_match_all("/'rota'\s*=>\s*'([^']+)'/", $blade, $encontrados);

        $rotas = array_unique($encontrados[1]);

        $this->assertNotEmpty($rotas, 'o dashboard precisa declarar pelo menos um atalho');

        foreach ($rotas as $rota) {
            $this->assertTrue(
                Route::has($rota),
                "o atalho do dashboard aponta para a rota inexistente '{$rota}'"
            );
        }
    }
}
