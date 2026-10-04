<?php

namespace Tests\Feature\Admin;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Application\CriarProdutoComSku;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SkuAssistidoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->active()->create();
        $this->company = Company::factory()->forCurrentTenant($this->tenant->id)->create();
        $this->admin = User::factory()->forTenant($this->tenant)->create();
        $p = app(ProvisionarEstabelecimento::class);
        $p->provisionarMatriz($this->tenant);
        $p->atribuirAdministrador($this->tenant, $this->admin);
        $this->actingAs($this->admin);
    }

    public static function nomes(): array
    {
        return [
            ['Leite Integral Italac 1L', 'LEITE-INTEGRAL-ITALAC-1L'],
            ['Café com açúcar', 'CAFE-COM-ACUCAR'],
            ['  Pão & queijo / 500g! ', 'PAO-QUEIJO-500G'],
            [str_repeat('a', 150), str_repeat('A', 100)],
            ['!!!', 'PRODUTO'],
        ];
    }

    #[DataProvider('nomes')]
    public function test_criacao_sem_sku_gera_codigo_seguro(string $nome, string $sku): void
    {
        $this->post(route('catalog.products.store'), $this->dados(['name' => $nome]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => $sku, 'name' => mb_strtoupper(trim($nome), 'UTF-8'), 'barcode' => null]);
    }

    public function test_colisao_inclui_arquivados_e_trunca_base_para_sufixo(): void
    {
        $base = str_repeat('A', 100);
        $existente = Product::factory()->create(['tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'sku' => $base]);
        $existente->delete();
        foreach ([2, 3] as $sufixo) {
            $this->post(route('catalog.products.store'), $this->dados(['name' => str_repeat('a', 150)]))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => str_repeat('A', 98).'-'.$sufixo]);
        }
    }

    public function test_codigo_de_outro_tenant_nao_interfere_e_ean_nao_vira_sku(): void
    {
        Product::factory()->create(['sku' => 'LEITE']);
        $this->post(route('catalog.products.store'), $this->dados(['barcode' => '7890000000001']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'LEITE', 'barcode' => '7890000000001']);
    }

    public function test_sugestao_automatica_e_recalculada_no_backend_e_resolve_colisao(): void
    {
        Product::factory()->create(['tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'sku' => 'LEITE']);
        $this->post(route('catalog.products.store'), $this->dados(['sku' => 'LEITE', 'sku_automatico' => '1']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'LEITE-2']);
    }

    public function test_codigo_manual_e_preservado_e_duplicado_e_recusado(): void
    {
        $dados = $this->dados(['sku' => 'meu_codigo-01', 'sku_automatico' => '0']);
        $this->post(route('catalog.products.store'), $dados)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'meu_codigo-01']);
        $this->post(route('catalog.products.store'), $dados)->assertSessionHasErrors('sku');
    }

    public function test_edit_preserva_codigo_ao_renomear_e_permite_alteracao_explicita(): void
    {
        $produto = Product::factory()->create(['tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'sku' => 'ORIGINAL']);
        $this->put(route('catalog.products.update', $produto), $this->dados(['name' => 'Novo nome', 'sku' => 'ORIGINAL']))->assertSessionHasNoErrors();
        $this->assertSame('ORIGINAL', $produto->fresh()->sku);
        $this->put(route('catalog.products.update', $produto), $this->dados(['sku' => 'CODIGO-NOVO']))->assertSessionHasNoErrors();
        $this->assertSame('CODIGO-NOVO', $produto->fresh()->sku);
    }

    public function test_formulario_ensina_codigo_interno_e_ean_separadamente(): void
    {
        $this->get(route('catalog.products.create'))->assertOk()->assertSee('SKU — código interno')
            ->assertSee('Gerar código')->assertSee('impresso abaixo do código de barras');
    }

    public function test_api_continua_exigindo_sku_e_preserva_codigo_manual(): void
    {
        $token = $this->admin->createToken('teste')->plainTextToken;
        $this->withToken($token)->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson('/api/v1/products', $this->dados())->assertUnprocessable()->assertJsonValidationErrors('sku');
        $this->withToken($token)->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson('/api/v1/products', $this->dados(['sku' => 'API-MANUAL']))->assertCreated()->assertJsonPath('data.sku', 'API-MANUAL');
    }

    public function test_conflito_real_da_constraint_gera_nova_tentativa_sem_produto_duplicado(): void
    {
        Product::factory()->create(['tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'sku' => 'LEITE']);
        $gerador = \Mockery::mock(CriarProdutoComSku::class)->makePartial();
        // Simula o candidato ficar ocupado entre a consulta e o INSERT.
        // A exception é produzida pela constraint real, não pelo mock.
        $gerador->shouldReceive('proximo')->twice()->andReturn('LEITE', 'LEITE-2');
        $this->app->instance(CriarProdutoComSku::class, $gerador);
        $this->post(route('catalog.products.store'), $this->dados())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'LEITE-2']);
        $this->assertSame(2, Product::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_geracao_nao_permite_empresa_de_outro_tenant(): void
    {
        $outra = Company::factory()->create();
        $this->post(route('catalog.products.store'), $this->dados(['company_id' => $outra->id]))->assertSessionHasErrors('company_id');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_codigo_manual_zero_nao_e_tratado_como_ausente(): void
    {
        $this->post(route('catalog.products.store'), $this->dados(['sku' => '0']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => '0']);
    }

    private function dados(array $mais = []): array
    {
        return ['company_id' => $this->company->id, 'name' => 'Leite', 'status' => 'active', 'unit' => 'UN', ...$mais];
    }
}
