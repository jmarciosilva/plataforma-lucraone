<?php

namespace Tests\Feature\Admin;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Products\Domain\Models\Category;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('onb-01b')]
class OnboardingChecklistTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->active()->create();
        $this->admin = User::factory()->forTenant($this->tenant)->create();
        $provisionamento = app(ProvisionarEstabelecimento::class);
        $provisionamento->provisionarMatriz($this->tenant);
        $provisionamento->atribuirAdministrador($this->tenant, $this->admin);
        $this->actingAs($this->admin);
    }

    public function test_cliente_novo_mostra_checklist_com_seis_pendencias(): void
    {
        $this->get(route('dashboard'))->assertOk()->assertSee('Primeiros passos');
        $this->assertSame([
            'estabelecimento' => true, 'empresa' => false, 'categoria' => false,
            'produto' => false, 'preco' => false, 'estoque' => false, 'equipe' => false,
        ], $this->estados());
    }

    public function test_empresa_e_categoria_concluem_apenas_seus_itens(): void
    {
        Company::factory()->forCurrentTenant($this->tenant->id)->create();
        $this->assertTrue($this->estados()['empresa']);
        $this->assertFalse($this->estados()['categoria']);
        Category::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertTrue($this->estados()['categoria']);
        $this->assertFalse($this->estados()['produto']);
    }

    public function test_produto_preco_de_venda_e_estoque_sao_derivados_individualmente(): void
    {
        $produto = $this->produto();
        $this->assertTrue($this->estados()['produto']);
        $this->assertFalse($this->estados()['preco']);
        Price::factory()->cost()->create(['tenant_id' => $this->tenant->id, 'product_id' => $produto->id]);
        $this->assertFalse($this->estados()['preco']);
        Price::factory()->sale()->create(['tenant_id' => $this->tenant->id, 'product_id' => $produto->id]);
        $this->assertTrue($this->estados()['preco']);
        $this->assertFalse($this->estados()['estoque']);
        Inventory::factory()->forProduct($produto)->create();
        $this->assertTrue($this->estados()['estoque']);
    }

    public function test_equipe_exige_segunda_identidade_com_conta_e_vinculo_ativos(): void
    {
        $this->assertFalse($this->estados()['equipe']);
        $colega = User::factory()->inactive()->forTenant($this->tenant)->create();
        $this->assertFalse($this->estados()['equipe']);
        $colega->update(['status' => 'ACTIVE']);
        $colega->joinTenant($this->tenant->id, 'INVITED');
        $this->assertFalse($this->estados()['equipe']);
        $colega->joinTenant($this->tenant->id, 'ACTIVE');
        $colega->joinTenant($this->tenant->id, 'ACTIVE');
        $this->assertTrue($this->estados()['equipe']);
        $colega->delete();
        $this->assertFalse($this->estados()['equipe']);
    }

    public function test_acesso_de_suporte_da_plataforma_nao_conclui_equipe(): void
    {
        User::factory()->platformAdmin()->forTenant($this->tenant)->create();
        $this->assertFalse($this->estados()['equipe']);
    }

    public function test_dados_de_outro_estabelecimento_nao_concluem_checklist(): void
    {
        $outro = Tenant::factory()->active()->create();
        $empresa = Company::factory()->forCurrentTenant($outro->id)->create();
        Category::factory()->create(['tenant_id' => $outro->id]);
        $produto = Product::factory()->create(['tenant_id' => $outro->id, 'company_id' => $empresa->id]);
        Price::factory()->sale()->create(['tenant_id' => $outro->id, 'product_id' => $produto->id]);
        Inventory::factory()->forProduct($produto)->create();
        User::factory(2)->forTenant($outro)->create();
        $this->assertSame([true, false, false, false, false, false, false], array_values($this->estados()));
    }

    public function test_checklist_completo_deixa_de_ocupar_o_dashboard(): void
    {
        $produto = $this->produto();
        Category::factory()->create(['tenant_id' => $this->tenant->id]);
        Price::factory()->sale()->create(['tenant_id' => $this->tenant->id, 'product_id' => $produto->id]);
        Inventory::factory()->forProduct($produto)->create();
        User::factory()->forTenant($this->tenant)->create();
        $this->assertNotContains(false, $this->estados());
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Primeiros passos');
    }

    public function test_preco_e_estoque_de_produto_arquivado_nao_concluem_itens(): void
    {
        $produto = $this->produto();
        Price::factory()->sale()->create(['tenant_id' => $this->tenant->id, 'product_id' => $produto->id]);
        Inventory::factory()->forProduct($produto)->create();
        $produto->delete();
        $this->assertFalse($this->estados()['produto']);
        $this->assertFalse($this->estados()['preco']);
        $this->assertFalse($this->estados()['estoque']);
    }

    public function test_sete_etapas_numeradas_mantem_ordem_status_e_links(): void
    {
        $itens = $this->itensRenderizados();
        $rotulos = ['Estabelecimento criado', 'Cadastrar empresa', 'Criar primeira categoria',
            'Cadastrar primeiro produto', 'Definir preço', 'Informar estoque inicial', 'Cadastrar equipe'];
        $rotas = [null, 'companies.create', 'catalog.categories.create', 'catalog.products.create',
            'catalog.products.index', 'inventory.index', 'users.create'];
        foreach ($rotulos as $indice => $rotulo) {
            $item = $itens->item($indice);
            $texto = preg_replace('/\s+/u', ' ', trim($item->textContent));
            $this->assertStringContainsString(($indice + 1).'. '.$rotulo, $texto);
            $this->assertSame(1, preg_match_all('/\b[1-7]\. /u', $texto));
            $this->assertSame($indice === 0 ? 'Concluído' : 'Pendente', $item->getElementsByTagName('span')->item(0)->getAttribute('aria-label'));
            $links = $item->getElementsByTagName('a');
            if ($rotas[$indice]) {
                $this->assertSame(1, $links->length);
                $this->assertSame(route($rotas[$indice]), $links->item(0)->getAttribute('href'));
            } else {
                $this->assertSame(0, $links->length);
            }
        }
    }

    public function test_empresa_concluida_mantem_numero_dois_e_pendentes_nao_sao_renumerados(): void
    {
        Company::factory()->forCurrentTenant($this->tenant->id)->create();
        $itens = $this->itensRenderizados();
        $empresa = $itens->item(1);
        $this->assertStringContainsString('2. Cadastrar empresa', $empresa->textContent);
        $this->assertSame('Concluído', $empresa->getElementsByTagName('span')->item(0)->getAttribute('aria-label'));
        $this->assertSame(0, $empresa->getElementsByTagName('a')->length);
        $categoria = $itens->item(2);
        $this->assertStringContainsString('3. Criar primeira categoria', $categoria->textContent);
        $this->assertSame('Pendente', $categoria->getElementsByTagName('span')->item(0)->getAttribute('aria-label'));
        $this->assertSame(route('catalog.categories.create'), $categoria->getElementsByTagName('a')->item(0)->getAttribute('href'));
        $this->assertStringContainsString('7. Cadastrar equipe', $itens->item(6)->textContent);
    }

    private function itensRenderizados(): \DOMNodeList
    {
        $resposta = $this->get(route('dashboard'))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$resposta->getContent());
        $xpath = new \DOMXPath($dom);
        $itens = $xpath->query('//ol[@aria-label="Etapas de configuração do estabelecimento"]/li');
        $this->assertCount(7, $itens);

        return $itens;
    }

    private function estados(): array
    {
        $resposta = $this->get(route('dashboard'))->assertOk();

        return collect($resposta->viewData('onboardingChecklist'))->pluck('concluido', 'id')->all();
    }

    private function produto(): Product
    {
        app(TenantContext::class)->set($this->tenant->id);
        $empresa = Company::factory()->forCurrentTenant($this->tenant->id)->create();

        return Product::factory()->create(['tenant_id' => $this->tenant->id, 'company_id' => $empresa->id]);
    }
}
