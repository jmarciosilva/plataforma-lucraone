<?php

namespace Tests\Feature\Products;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cadastro mestre — etapa 1: código de barras e unidade base.
 *
 * `barcode` é o código da apresentação comercial (opcional, único por tenant);
 * `unit` diz o que significa quantity = 1 no estoque e na venda (UN ou KG).
 */
class ProductMasterDataApiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Company $company;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->company, $this->token] = $this->contexto();
    }

    public function test_produto_pode_ser_criado_sem_barcode_e_assume_unidade_un(): void
    {
        $this->criar(['sku' => 'MAS-ART-001', 'name' => 'Massa artesanal'])
            ->assertStatus(201)
            ->assertJsonPath('data.barcode', null)
            ->assertJsonPath('data.unit', 'UN');

        $this->assertDatabaseHas('products', [
            'sku' => 'MAS-ART-001',
            'barcode' => null,
            'unit' => 'UN',
        ]);
    }

    public function test_produto_pode_ser_criado_com_barcode(): void
    {
        $this->criar(['sku' => 'REF-350', 'barcode' => '7890000000001', 'unit' => 'UN'])
            ->assertStatus(201)
            ->assertJsonPath('data.barcode', '7890000000001')
            ->assertJsonPath('data.unit', 'UN');

        $this->assertDatabaseHas('products', [
            'tenant_id' => $this->tenant->id,
            'sku' => 'REF-350',
            'barcode' => '7890000000001',
        ]);
    }

    public function test_unidade_kg_e_aceita(): void
    {
        $this->criar(['sku' => 'MAC-FRE-KG', 'unit' => 'KG'])
            ->assertStatus(201)
            ->assertJsonPath('data.unit', 'KG');
    }

    public function test_unidade_fora_da_allowlist_e_recusada(): void
    {
        foreach (['LT', 'kg', 'CX', ''] as $unidade) {
            $this->criar(['sku' => 'INV-'.Str::random(4), 'unit' => $unidade])
                ->assertStatus(422)
                ->assertJsonValidationErrors('unit');
        }
    }

    public function test_barcode_maior_que_gtin14_e_recusado(): void
    {
        $this->criar(['sku' => 'LONGO', 'barcode' => '123456789012345'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('barcode');
    }

    public function test_barcode_duplicado_no_mesmo_tenant_e_recusado(): void
    {
        $this->produto(['barcode' => '7890000000001']);

        $this->criar(['sku' => 'OUTRO', 'barcode' => '7890000000001'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('barcode');
    }

    public function test_mesmo_barcode_em_tenants_diferentes_e_permitido(): void
    {
        [$outroTenant, $outraEmpresa] = $this->contexto();

        Product::factory()->create([
            'tenant_id' => $outroTenant->id,
            'company_id' => $outraEmpresa->id,
            'barcode' => '7890000000001',
        ]);

        $this->criar(['sku' => 'REF-350', 'barcode' => '7890000000001'])
            ->assertStatus(201);

        $this->assertSame(2, Product::withoutGlobalScopes()->where('barcode', '7890000000001')->count());
    }

    public function test_varios_produtos_sem_barcode_no_mesmo_tenant(): void
    {
        $this->criar(['sku' => 'SEM-1'])->assertStatus(201);
        $this->criar(['sku' => 'SEM-2', 'barcode' => null])->assertStatus(201);
        $this->criar(['sku' => 'SEM-3', 'barcode' => ''])->assertStatus(201);

        $this->assertSame(3, Product::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('barcode')
            ->count());
    }

    public function test_registro_existente_sem_os_novos_campos_fica_un_e_sem_barcode(): void
    {
        $id = (string) Str::ulid();

        DB::table('products')->insert([
            'id' => $id,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'sku' => 'LEGADO-001',
            'name' => 'Produto legado',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('products', ['id' => $id, 'barcode' => null, 'unit' => 'UN']);
    }

    public function test_update_mantem_o_proprio_barcode(): void
    {
        $product = $this->produto(['barcode' => '7890000000001']);

        $this->atualizar($product, ['barcode' => '7890000000001', 'name' => 'Refrigerante lata'])
            ->assertStatus(200)
            ->assertJsonPath('data.barcode', '7890000000001');
    }

    public function test_update_recusa_barcode_de_outro_produto_do_tenant(): void
    {
        $this->produto(['barcode' => '7890000000001']);
        $product = $this->produto(['barcode' => '7890000000002']);

        $this->atualizar($product, ['barcode' => '7890000000001'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('barcode');
    }

    public function test_update_altera_unidade_e_recusa_unidade_invalida(): void
    {
        $product = $this->produto();

        $this->atualizar($product, ['unit' => 'KG'])
            ->assertStatus(200)
            ->assertJsonPath('data.unit', 'KG');

        $this->atualizar($product, ['unit' => 'L'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('unit');
    }

    public function test_update_sem_os_novos_campos_preserva_valores(): void
    {
        $product = $this->produto(['barcode' => '7890000000001', 'unit' => 'KG']);

        $this->atualizar($product, ['name' => 'Só o nome'])
            ->assertStatus(200)
            ->assertJsonPath('data.barcode', '7890000000001')
            ->assertJsonPath('data.unit', 'KG');
    }

    public function test_busca_por_barcode_encontra_o_produto(): void
    {
        $this->produto(['sku' => 'REF-600', 'name' => 'Refrigerante 600 ml', 'barcode' => '7890000000600']);
        $this->produto(['sku' => 'REF-2L', 'name' => 'Refrigerante 2 l', 'barcode' => '7890000002000']);

        $resposta = $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/products/search/7890000000600')
            ->assertStatus(200);

        $this->assertSame(['REF-600'], collect($resposta->json('data'))->pluck('sku')->all());
    }

    public function test_resource_expoe_barcode_e_unit(): void
    {
        $product = $this->produto(['barcode' => '7890000000001', 'unit' => 'KG']);

        $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson("/api/v1/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.barcode', '7890000000001')
            ->assertJsonPath('data.unit', 'KG');
    }

    private function contexto(): array
    {
        $tenant = Tenant::factory()->create();
        $company = Company::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        return [$tenant, $company, $user->createToken('test')->plainTextToken];
    }

    private function produto(array $atributos = []): Product
    {
        return Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            ...$atributos,
        ]);
    }

    private function criar(array $dados)
    {
        return $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson('/api/v1/products', [
                'company_id' => $this->company->id,
                'name' => 'Produto',
                'status' => 'active',
                ...$dados,
            ]);
    }

    private function atualizar(Product $product, array $dados)
    {
        return $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->putJson("/api/v1/products/{$product->id}", $dados);
    }
}
