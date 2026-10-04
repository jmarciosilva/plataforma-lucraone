<?php

namespace Tests\Feature\Admin;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AjudaCadastroClienteTest extends TestCase
{
    use RefreshDatabase;

    private User $operador;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::factory()->active()->create();
        $this->operador = User::factory()->platformAdmin()->forTenant($tenant)->create();
        $this->administrador = User::factory()->forTenant($tenant)->create();
        $provisionamento = app(ProvisionarEstabelecimento::class);
        $provisionamento->provisionarMatriz($tenant);
        $provisionamento->atribuirAdministrador($tenant, $this->administrador);
    }

    public function test_operador_encontra_acao_e_orientacao_no_dashboard(): void
    {
        $this->actingAs($this->operador)->get(route('dashboard'))->assertOk()
            ->assertSee('Administração do LucraOne')->assertSee('Cadastrar novo cliente')
            ->assertSee('Como cadastrar um cliente')
            ->assertSee('href="'.route('tenants.create').'"', false)
            ->assertSee('href="'.route('tenants.onboarding.help').'"', false);
    }

    public function test_administrador_local_nao_recebe_acao_ou_ajuda_de_plataforma(): void
    {
        $this->actingAs($this->administrador)->get(route('dashboard'))->assertOk()
            ->assertDontSee('Cadastrar novo cliente')->assertDontSee('Como cadastrar um cliente')
            ->assertDontSee('/tenants/ajuda/cadastro');
    }

    public function test_ajuda_ensina_etapas_decisoes_e_proximos_passos_sem_jargao(): void
    {
        $response = $this->actingAs($this->operador)->get('/tenants/ajuda/cadastro')->assertOk();
        foreach (['Estabelecimento', 'Administrador', 'Configuração inicial', 'Revisão',
            'Identificador', 'Plano', 'Situação', 'Fuso horário', 'Idioma', 'Moeda',
            'senha atual', 'não é alterada', 'confirmação final', 'Próximos passos',
            'categoria', 'produto', 'preço', 'estoque inicial', 'equipe',
            'Massas do João', 'joao@exemplo.com.br', 'Dicas importantes'] as $conceito) {
            $response->assertSee($conceito);
        }
        preg_match('/<article\b[^>]*>(.*?)<\/article>/s', $response->getContent(), $conteudo);
        $this->assertNotEmpty($conteudo);
        $texto = html_entity_decode(strip_tags($conteudo[1]));
        $this->assertDoesNotMatchRegularExpression('/\b(tenant|tenant_id|pivot|role|permission|policy|scope|middleware)\b/i', $texto);
        $response->assertSee('href="'.route('tenants.create').'"', false)->assertDontSee('href="#"', false);
    }

    public function test_ajuda_pode_ser_reaberta_sem_flash_e_nao_cria_entidades(): void
    {
        $antes = [Tenant::count(), User::count()];
        $this->actingAs($this->operador)->get('/tenants/ajuda/cadastro')->assertOk();
        $this->get('/tenants/ajuda/cadastro')->assertOk()->assertSee('Como cadastrar um novo cliente no LucraOne');
        $this->assertSame($antes, [Tenant::count(), User::count()]);
    }

    public function test_ajuda_e_operacao_recusam_administrador_local_no_backend(): void
    {
        $this->actingAs($this->administrador)->get('/tenants/ajuda/cadastro')->assertForbidden();
        $this->get(route('tenants.create'))->assertForbidden();
        $this->post(route('tenants.onboarding.store'), [])->assertForbidden();
    }

    public function test_visitante_nao_acessa_ajuda(): void
    {
        $this->get('/tenants/ajuda/cadastro')->assertRedirect(route('login'));
    }

    public function test_lista_e_wizard_oferecem_link_real_para_ajuda(): void
    {
        $this->actingAs($this->operador)->get(route('tenants.index'))->assertOk()
            ->assertSee('novo cliente')->assertSee('Como cadastrar um cliente')
            ->assertSee('href="'.route('tenants.create').'"', false)
            ->assertSee('href="'.route('tenants.onboarding.help').'"', false);
        $this->get(route('tenants.create'))->assertOk()->assertSee('Precisa de ajuda?')
            ->assertSee('href="'.route('tenants.onboarding.help').'"', false)
            ->assertSee('target="_blank"', false)->assertSee('rel="noopener"', false);
    }

    public function test_rotas_citadas_pelos_conteudos_alterados_existem(): void
    {
        foreach (['dashboard/index', 'tenants/index', 'tenants/create', 'tenants/onboarding-help'] as $view) {
            $blade = file_get_contents(resource_path('views/'.$view.'.blade.php'));
            preg_match_all("/route\('([^']+)'/", $blade, $matches);
            $this->assertNotEmpty($matches[1]);
            foreach (array_unique($matches[1]) as $route) {
                $this->assertTrue(Route::has($route), "Rota inexistente: {$route}");
            }
        }
    }
}
