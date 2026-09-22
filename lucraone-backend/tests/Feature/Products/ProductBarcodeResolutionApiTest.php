<?php

namespace Tests\Feature\Products;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Products\Application\ProductBarcodeResolver;
use App\Modules\Products\Domain\Exceptions\BarcodeConflictException;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Models\ProductPackage;
use App\Modules\Sales\Domain\Models\OrderItem;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PM-03 — resolução exata de código de barras, preparando o futuro scanner.
 *
 * Código de barras → Product base + quantidade na unidade base (1 para o
 * código do produto, `factor` para o da embalagem). Só resolve: não vende, não
 * movimenta estoque, não decide preço. Fora do escopo: scanner físico, código
 * de balança / GTIN de quantidade variável, etiqueta e EAN alternativo (fator 1).
 */
class ProductBarcodeResolutionApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Company $company;

    private string $token;

    private Product $lata;

    private ProductPackage $caixa24;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->company, $this->token] = $this->contexto();

        $this->lata = $this->produto($this->tenant, $this->company, ['name' => 'Coca-Cola 350 ml', 'barcode' => '7890000000350']);
        $this->caixa24 = $this->embalagem($this->lata, ['name' => 'Caixa 24', 'barcode' => '17890000000357', 'factor' => 24]);
    }

    public function test_codigo_do_produto_resolve_para_uma_unidade_sem_embalagem(): void
    {
        $this->resolver('7890000000350')
            ->assertOk()
            ->assertJsonPath('data.product.id', (string) $this->lata->id)
            ->assertJsonPath('data.product.name', 'Coca-Cola 350 ml')
            ->assertJsonPath('data.barcode', '7890000000350')
            ->assertJsonPath('data.source', 'product')
            ->assertJsonPath('data.quantity', 1)
            ->assertJsonPath('data.unit', 'UN')
            ->assertJsonPath('data.package', null);
    }

    public function test_codigo_da_embalagem_resolve_para_o_produto_base_com_o_fator(): void
    {
        $this->resolver('17890000000357')
            ->assertOk()
            ->assertJsonPath('data.product.id', (string) $this->lata->id)
            ->assertJsonPath('data.barcode', '17890000000357')
            ->assertJsonPath('data.source', 'package')
            ->assertJsonPath('data.quantity', 24)
            ->assertJsonPath('data.unit', 'UN')
            ->assertJsonPath('data.package.id', (string) $this->caixa24->id)
            ->assertJsonPath('data.package.name', 'Caixa 24')
            ->assertJsonPath('data.package.barcode', '17890000000357')
            ->assertJsonPath('data.package.factor', 24);
    }

    public function test_codigo_inexistente_retorna_404(): void
    {
        $this->resolver('7899999999999')
            ->assertJsonPath('message', 'código de barras não encontrado.')->assertNotFound();
    }

    public function test_resolucao_e_exata_e_nao_por_trecho(): void
    {
        foreach (['789000000035', '78900000003501', 'X7890000000350', '1789000000035', '0000000350'] as $parcial) {
            $this->resolver($parcial)->assertJsonPath('message', 'código de barras não encontrado.')->assertNotFound();
        }
    }

    public function test_codigo_de_outro_tenant_nao_resolve(): void
    {
        [$outroTenant, $outraEmpresa] = $this->contexto();
        $alheio = $this->produto($outroTenant, $outraEmpresa, ['barcode' => '7890000000999']);
        $this->embalagem($alheio, ['barcode' => '17890000000999', 'factor' => 12]);

        $this->resolver('7890000000999')->assertJsonPath('message', 'código de barras não encontrado.')->assertNotFound();
        $this->resolver('17890000000999')->assertJsonPath('message', 'código de barras não encontrado.')->assertNotFound();
    }

    public function test_mesmo_codigo_em_dois_tenants_resolve_o_registro_do_tenant_da_requisicao(): void
    {
        [$outroTenant, $outraEmpresa, $outroToken] = $this->contexto();
        $alheio = $this->produto($outroTenant, $outraEmpresa, ['barcode' => '7890000000350']);
        $caixaAlheia = $this->embalagem($alheio, ['barcode' => '17890000000357', 'factor' => 12]);

        $this->resolver('7890000000350')->assertJsonPath('data.product.id', (string) $this->lata->id);
        $this->resolver('17890000000357')->assertJsonPath('data.quantity', 24);

        $this->resolver('7890000000350', $outroTenant, $outroToken)
            ->assertJsonPath('data.product.id', (string) $alheio->id);
        $this->resolver('17890000000357', $outroTenant, $outroToken)
            ->assertJsonPath('data.package.id', (string) $caixaAlheia->id)
            ->assertJsonPath('data.quantity', 12);
    }

    public function test_rota_nao_conflita_com_show_nem_com_search(): void
    {
        $this->resolver('7890000000350')->assertJsonPath('data.source', 'product');

        $this->api()->getJson("/api/v1/products/{$this->lata->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $this->lata->id)
            ->assertJsonMissingPath('data.source');

        // a busca manual por trecho continua por LIKE
        $this->api()->getJson('/api/v1/products/search/789000000035')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $this->lata->id);
    }

    public function test_so_resolve_produto_ativo_e_nao_arquivado(): void
    {
        $inativo = $this->produto($this->tenant, $this->company, ['barcode' => '7890000000001', 'status' => 'inactive']);
        $descontinuado = $this->produto($this->tenant, $this->company, ['barcode' => '7890000000002', 'status' => 'discontinued']);
        $arquivado = $this->produto($this->tenant, $this->company, ['barcode' => '7890000000003']);
        $arquivado->delete();

        $this->resolver('7890000000001')->assertJsonPath('message', 'código de barras não encontrado.')->assertNotFound();
        $this->resolver('7890000000002')->assertJsonPath('message', 'código de barras não encontrado.')->assertNotFound();
        $this->resolver('7890000000003')->assertJsonPath('message', 'código de barras não encontrado.')->assertNotFound();
    }

    public function test_embalagem_de_produto_indisponivel_nao_resolve(): void
    {
        $inativo = $this->produto($this->tenant, $this->company, ['status' => 'inactive']);
        $this->embalagem($inativo, ['barcode' => '17890000000001', 'factor' => 6]);

        $arquivado = $this->produto($this->tenant, $this->company);
        $this->embalagem($arquivado, ['barcode' => '17890000000002', 'factor' => 6]);
        $arquivado->delete();

        $this->resolver('17890000000001')->assertJsonPath('message', 'código de barras não encontrado.')->assertNotFound();
        $this->resolver('17890000000002')->assertJsonPath('message', 'código de barras não encontrado.')->assertNotFound();
    }

    public function test_codigo_maior_que_14_caracteres_e_recusado(): void
    {
        $this->resolver('178900000003570')
            ->assertStatus(422)
            ->assertJsonValidationErrors('barcode');
    }

    public function test_produto_kg_com_codigo_resolve_uma_unidade_de_kg_sem_interpretar_balanca(): void
    {
        $this->produto($this->tenant, $this->company, ['name' => 'Massa fresca', 'unit' => 'KG', 'barcode' => '2000000000015']);

        $this->resolver('2000000000015')
            ->assertOk()
            ->assertJsonPath('data.source', 'product')
            ->assertJsonPath('data.quantity', 1)
            ->assertJsonPath('data.unit', 'KG');

        // código de balança com peso embutido não é interpretado nesta etapa
        $this->resolver('2000000003509')->assertJsonPath('message', 'código de barras não encontrado.')->assertNotFound();
    }

    public function test_resolver_nao_movimenta_estoque_nem_cria_item_de_pedido(): void
    {
        $this->resolver('7890000000350')->assertOk();
        $this->resolver('17890000000357')->assertOk();

        $this->assertSame(0, InventoryMovement::withoutGlobalScopes()->count());
        $this->assertSame(0, OrderItem::withoutGlobalScopes()->count());
        $this->assertDatabaseCount('inventories', 0);
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/v1/products/resolve-barcode/7890000000350')->assertUnauthorized();
    }

    public function test_colisao_entre_produto_e_embalagem_nao_e_mascarada(): void
    {
        // Estado que a validação (BarcodeAvailable) impede; aqui é forçado no banco.
        DB::table('product_packages')->where('id', $this->caixa24->id)->update(['barcode' => '7890000000350']);

        $this->resolver('7890000000350')
            ->assertStatus(409)
            ->assertJsonMissingPath('data');

        $this->expectException(BarcodeConflictException::class);

        app(ProductBarcodeResolver::class)->resolve((string) $this->tenant->id, '7890000000350');
    }

    public function test_resolver_recebe_o_tenant_explicitamente(): void
    {
        $resolver = app(ProductBarcodeResolver::class);

        $resolvido = $resolver->resolve((string) $this->tenant->id, '17890000000357');

        $this->assertSame((string) $this->lata->id, (string) $resolvido->product->id);
        $this->assertSame(24, $resolvido->quantity);
        $this->assertSame('package', $resolvido->source);
        $this->assertSame((string) $this->caixa24->id, (string) $resolvido->package->id);

        [$outroTenant] = $this->contexto();
        $this->assertNull($resolver->resolve((string) $outroTenant->id, '17890000000357'));
    }

    private function resolver(string $barcode, ?Tenant $tenant = null, ?string $token = null)
    {
        return $this->api($tenant, $token)->getJson("/api/v1/products/resolve-barcode/{$barcode}");
    }

    private function api(?Tenant $tenant = null, ?string $token = null): static
    {
        // O guard guarda o usuário da requisição anterior; sem isto, trocar de
        // token no mesmo teste reaproveitaria o primeiro usuário.
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)
            ->withHeader('X-Tenant-ID', (string) ($tenant ?? $this->tenant)->id);
    }

    private function contexto(): array
    {
        $tenant = Tenant::factory()->create();
        $company = Company::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        return [$tenant, $company, $user->createToken('test')->plainTextToken];
    }

    private function produto(Tenant $tenant, Company $company, array $atributos = []): Product
    {
        return Product::factory()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            ...$atributos,
        ]);
    }

    private function embalagem(Product $product, array $atributos = []): ProductPackage
    {
        return ProductPackage::factory()->create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            ...$atributos,
        ]);
    }
}
