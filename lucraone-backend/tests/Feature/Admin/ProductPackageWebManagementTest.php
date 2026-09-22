<?php

namespace Tests\Feature\Admin;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Models\ProductPackage;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PM-02A — embalagens comerciais (caixa, fardo, multipack) do Product base.
 *
 * O Product continua sendo a unidade de estoque e venda; a embalagem só diz
 * quantas unidades base ela contém (`factor`) e qual código de barras a
 * identifica. Código de barras é único no estabelecimento, somando produtos e
 * embalagens.
 */
class ProductPackageWebManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantAtual;

    private Tenant $outroTenant;

    private User $admin;

    private Company $company;

    private Product $lata;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantAtual = Tenant::factory()->active()->create(['name' => 'Casa Alta']);
        $this->outroTenant = Tenant::factory()->active()->create(['name' => 'Mercado Norte']);
        $this->company = Company::factory()->forCurrentTenant($this->tenantAtual->id)->active()->create();

        $this->admin = User::factory()->forTenant($this->tenantAtual)->create();
        $this->tornarAdmin($this->admin, $this->tenantAtual);

        $this->lata = $this->produto(['name' => 'Coca-Cola 350 ml', 'barcode' => '7890000000350']);
    }

    public function test_cria_embalagem_valida_no_tenant_atual(): void
    {
        $this->criar($this->lata, ['name' => 'Caixa 24', 'barcode' => '17890000000357', 'factor' => 24])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('catalog.products.show', $this->lata));

        $this->assertDatabaseHas('product_packages', [
            'tenant_id' => $this->tenantAtual->id,
            'product_id' => $this->lata->id,
            'name' => 'Caixa 24',
            'barcode' => '17890000000357',
            'factor' => 24,
        ]);
    }

    public function test_fator_minimo_e_dois(): void
    {
        $this->criar($this->lata, ['name' => 'Pack 2', 'factor' => 2])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_packages', ['product_id' => $this->lata->id, 'factor' => 2]);
    }

    public function test_fator_invalido_e_recusado(): void
    {
        foreach ([1, 0, -6, 2.5, '6.0', 'seis', null] as $fator) {
            $this->criar($this->lata, ['name' => 'Inválida', 'factor' => $fator])
                ->assertSessionHasErrors('factor');
        }

        $this->assertDatabaseCount('product_packages', 0);
    }

    public function test_nome_e_obrigatorio(): void
    {
        $this->criar($this->lata, ['name' => '', 'factor' => 6])->assertSessionHasErrors('name');
    }

    public function test_produto_kg_nao_aceita_embalagem(): void
    {
        $massa = $this->produto(['name' => 'Massa fresca', 'unit' => 'KG']);

        $this->criar($massa, ['name' => 'Caixa 5', 'factor' => 5])->assertSessionHasErrors('unit');

        $this->assertDatabaseCount('product_packages', 0);
        $this->assertSame('KG', $massa->fresh()->unit);
    }

    public function test_barcode_opcional_e_varias_embalagens_sem_codigo(): void
    {
        $this->criar($this->lata, ['name' => 'Fardo 6', 'factor' => 6])->assertSessionHasNoErrors();
        $this->criar($this->lata, ['name' => 'Fardo 12', 'barcode' => '', 'factor' => 12])->assertSessionHasNoErrors();

        $this->assertSame(2, ProductPackage::query()->whereNull('barcode')->count());
    }

    public function test_barcode_repetido_entre_embalagens_do_mesmo_tenant_e_recusado(): void
    {
        $garrafa = $this->produto(['name' => 'Refrigerante 2 L']);
        $this->embalagem($this->lata, ['barcode' => '17890000000357']);

        $this->criar($garrafa, ['name' => 'Fardo 6', 'barcode' => '17890000000357', 'factor' => 6])
            ->assertSessionHasErrors('barcode');
    }

    public function test_barcode_da_embalagem_nao_pode_ser_de_um_produto_do_tenant(): void
    {
        $this->produto(['barcode' => '7890000000600']);

        $this->criar($this->lata, ['name' => 'Caixa 24', 'barcode' => '7890000000600', 'factor' => 24])
            ->assertSessionHasErrors('barcode');

        // inclusive o do próprio produto base
        $this->criar($this->lata, ['name' => 'Caixa 24', 'barcode' => '7890000000350', 'factor' => 24])
            ->assertSessionHasErrors('barcode');
    }

    public function test_barcode_do_produto_nao_pode_ser_de_uma_embalagem_do_tenant(): void
    {
        $this->embalagem($this->lata, ['barcode' => '17890000000357']);

        $this->actingAs($this->admin)
            ->post(route('catalog.products.store'), $this->dadosProduto(['barcode' => '17890000000357']))
            ->assertSessionHasErrors('barcode');

        $garrafa = $this->produto();

        $this->actingAs($this->admin)
            ->put(route('catalog.products.update', $garrafa), $this->dadosProduto([
                'sku' => $garrafa->sku,
                'barcode' => '17890000000357',
            ]))
            ->assertSessionHasErrors('barcode');

        // o produto continua podendo manter o próprio código
        $this->actingAs($this->admin)
            ->put(route('catalog.products.update', $this->lata), $this->dadosProduto([
                'sku' => $this->lata->sku,
                'barcode' => '7890000000350',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_mesmo_barcode_em_outro_tenant_e_permitido(): void
    {
        $produtoAlheio = Product::factory()->create([
            'tenant_id' => $this->outroTenant->id,
            'barcode' => '7890000000999',
        ]);
        ProductPackage::factory()->create([
            'tenant_id' => $this->outroTenant->id,
            'product_id' => $produtoAlheio->id,
            'barcode' => '17890000000357',
        ]);

        $this->criar($this->lata, ['name' => 'Caixa 24', 'barcode' => '17890000000357', 'factor' => 24])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('catalog.products.store'), $this->dadosProduto(['barcode' => '7890000000999']))
            ->assertSessionHasNoErrors();
    }

    public function test_nao_cria_embalagem_para_produto_de_outro_tenant(): void
    {
        $produtoAlheio = Product::factory()->create(['tenant_id' => $this->outroTenant->id]);

        $this->criar($produtoAlheio, ['name' => 'Caixa 24', 'factor' => 24])->assertNotFound();

        $this->assertDatabaseCount('product_packages', 0);
    }

    public function test_remove_embalagem_do_proprio_produto(): void
    {
        $caixa = $this->embalagem($this->lata);

        $this->actingAs($this->admin)
            ->delete(route('catalog.products.packages.destroy', [$this->lata, $caixa]))
            ->assertRedirect(route('catalog.products.show', $this->lata));

        $this->assertDatabaseMissing('product_packages', ['id' => $caixa->id]);
        $this->assertNotSoftDeleted($this->lata);
    }

    public function test_nao_remove_embalagem_de_outro_produto_trocando_a_url(): void
    {
        $garrafa = $this->produto();
        $fardo = $this->embalagem($garrafa);

        $this->actingAs($this->admin)
            ->delete(route('catalog.products.packages.destroy', [$this->lata, $fardo]))
            ->assertNotFound();

        $this->assertDatabaseHas('product_packages', ['id' => $fardo->id]);
    }

    public function test_nao_remove_embalagem_de_outro_tenant(): void
    {
        $produtoAlheio = Product::factory()->create(['tenant_id' => $this->outroTenant->id]);
        $caixaAlheia = ProductPackage::factory()->create([
            'tenant_id' => $this->outroTenant->id,
            'product_id' => $produtoAlheio->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('catalog.products.packages.destroy', [$this->lata, $caixaAlheia]))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->delete(route('catalog.products.packages.destroy', [$produtoAlheio, $caixaAlheia]))
            ->assertNotFound();

        $this->assertDatabaseHas('product_packages', ['id' => $caixaAlheia->id]);
    }

    public function test_usuario_sem_permissao_nao_gerencia_embalagens(): void
    {
        $caixa = $this->embalagem($this->lata);
        $usuario = User::factory()->forTenant($this->tenantAtual)->create();

        $this->actingAs($usuario)
            ->post(route('catalog.products.packages.store', $this->lata), ['name' => 'Fardo 6', 'factor' => 6])
            ->assertForbidden();

        $this->actingAs($usuario)
            ->delete(route('catalog.products.packages.destroy', [$this->lata, $caixa]))
            ->assertForbidden();

        $this->assertDatabaseCount('product_packages', 1);
    }

    public function test_detalhe_do_produto_un_lista_embalagens_e_oferece_cadastro(): void
    {
        $this->embalagem($this->lata, ['name' => 'Caixa 24', 'barcode' => '17890000000357', 'factor' => 24]);
        $this->embalagem($this->lata, ['name' => 'Fardo 6', 'barcode' => null, 'factor' => 6]);

        $resposta = $this->actingAs($this->admin)
            ->get(route('catalog.products.show', $this->lata))
            ->assertOk()
            ->assertSee('embalagens')
            ->assertSee(route('catalog.products.packages.store', $this->lata))
            ->assertSee('Caixa 24')
            ->assertSee('17890000000357')
            ->assertSee('Fardo 6')
            ->assertSee('24 UN');

        $this->assertCount(2, $resposta->viewData('product')->packages);
    }

    public function test_detalhe_do_produto_kg_nao_oferece_cadastro_de_embalagem(): void
    {
        $massa = $this->produto(['unit' => 'KG']);

        $this->actingAs($this->admin)
            ->get(route('catalog.products.show', $massa))
            ->assertOk()
            ->assertDontSee(route('catalog.products.packages.store', $massa))
            ->assertSee('apenas para produtos com unidade UN');
    }

    public function test_indice_unico_de_barcode_por_tenant_e_varios_nulos_no_banco(): void
    {
        $this->embalagem($this->lata, ['barcode' => null]);
        $this->embalagem($this->lata, ['barcode' => null]);
        $this->embalagem($this->lata, ['barcode' => '17890000000357']);

        $this->expectException(QueryException::class);

        DB::table('product_packages')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantAtual->id,
            'product_id' => $this->lata->id,
            'name' => 'Duplicada',
            'barcode' => '17890000000357',
            'factor' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_embalagens_saem_junto_com_a_exclusao_definitiva_do_produto(): void
    {
        $caixa = $this->embalagem($this->lata);

        $this->lata->delete();
        $this->assertDatabaseHas('product_packages', ['id' => $caixa->id]);

        $this->lata->forceDelete();
        $this->assertDatabaseMissing('product_packages', ['id' => $caixa->id]);
    }

    private function criar(Product $product, array $dados)
    {
        return $this->actingAs($this->admin)
            ->from(route('catalog.products.show', $product))
            ->post(route('catalog.products.packages.store', $product), $dados);
    }

    private function produto(array $atributos = []): Product
    {
        return Product::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'company_id' => $this->company->id,
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

    private function dadosProduto(array $dados = []): array
    {
        return [
            'company_id' => (string) $this->company->id,
            'sku' => 'SKU-'.Str::random(6),
            'name' => 'Produto',
            'status' => 'active',
            'unit' => 'UN',
            ...$dados,
        ];
    }

    private function tornarAdmin(User $user, Tenant $tenant): void
    {
        $role = Role::factory()->admin()->forTenant($tenant->id)->create(['id' => (string) Str::ulid()]);

        foreach (['manage-products', 'view-products'] as $permissionName) {
            $role->grantPermission(Permission::factory()->forTenant($tenant->id)->create([
                'name' => $permissionName,
                'description' => $permissionName,
            ]));
        }

        $user->assignRole($role, $tenant->id);
    }
}
