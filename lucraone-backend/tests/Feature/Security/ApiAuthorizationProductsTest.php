<?php

namespace Tests\Feature\Security;

use App\Modules\Products\Domain\Models\Category;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;

/**
 * SEC-01 — autorização das 21 rotas do módulo Products.
 *
 * ProductController (8), CategoryController (7) e PriceController (6).
 *
 * Preço não tem Policy própria: o painel web autoriza preço pela
 * `ProductPolicy` do produto dono (`update` para escrever, leitura junto da
 * ficha). A API passa a usar a mesma regra, em vez de criar permissão nova.
 */
class ApiAuthorizationProductsTest extends ApiAuthorizationTestCase
{
    private function produto(?string $sku = null): Product
    {
        return Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'sku' => $sku ?? 'SEC01-'.fake()->unique()->numerify('####'),
            'status' => 'active',
        ]);
    }

    private function categoria(?string $slug = null): Category
    {
        return Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'slug' => $slug ?? 'sec01-'.fake()->unique()->numerify('####'),
            'parent_id' => null,
        ]);
    }

    // ---------------------------------------------------------------
    // ProductController — escrita barrada
    // ---------------------------------------------------------------

    public function test_leitor_nao_cria_produto(): void
    {
        $this->assertNegadoPorAutorizacao(
            $this->comoLeitor()->postJson('/api/v1/products', [
                'company_id' => $this->company->id,
                'sku' => 'SEC01-NOVO',
                'name' => 'Produto Proibido',
                'status' => 'active',
            ])
        );

        $this->assertDatabaseMissing('products', ['sku' => 'SEC01-NOVO']);
    }

    public function test_leitor_nao_atualiza_produto(): void
    {
        $produto = $this->produto();

        $this->assertNegadoPorAutorizacao(
            $this->comoLeitor()->putJson("/api/v1/products/{$produto->id}", ['name' => 'Renomeado'])
        );

        $this->assertSame($produto->name, $produto->fresh()->name);
    }

    public function test_leitor_nao_apaga_produto(): void
    {
        $produto = $this->produto();

        $this->assertNegadoPorAutorizacao(
            $this->comoLeitor()->deleteJson("/api/v1/products/{$produto->id}")
        );

        $this->assertNotSoftDeleted($produto);
    }

    // ---------------------------------------------------------------
    // ProductController — leitura liberada para viewer
    // ---------------------------------------------------------------

    public function test_leitor_lista_e_le_produtos(): void
    {
        $produto = $this->produto('SEC01-LEITURA');

        $this->comoLeitor()->getJson('/api/v1/products')->assertOk();
        $this->comoLeitor()->getJson("/api/v1/products/{$produto->id}")->assertOk();
        $this->comoLeitor()->getJson('/api/v1/products/search/SEC01')->assertOk();
        $this->comoLeitor()->getJson('/api/v1/products/status/active')->assertOk();
    }

    public function test_leitor_resolve_codigo_de_barras(): void
    {
        $produto = $this->produto();
        $produto->forceFill(['barcode' => '7891000100103'])->save();

        $this->comoLeitor()
            ->getJson('/api/v1/products/resolve-barcode/7891000100103')
            ->assertOk()
            ->assertJsonPath('data.product.id', (string) $produto->id);
    }

    // ---------------------------------------------------------------
    // ProductController — caminho positivo
    // ---------------------------------------------------------------

    public function test_gerente_cria_atualiza_e_apaga_produto(): void
    {
        $criado = $this->comoGerente()->postJson('/api/v1/products', [
            'company_id' => $this->company->id,
            'sku' => 'SEC01-OK',
            'name' => 'Produto Permitido',
            'status' => 'active',
        ])->assertCreated();

        $id = $criado->json('data.id');

        $this->comoGerente()
            ->putJson("/api/v1/products/{$id}", ['name' => 'Produto Renomeado'])
            ->assertOk()
            // O cadastro normaliza o nome em maiúsculas (ProductNameUppercaseTest).
            ->assertJsonPath('data.name', 'PRODUTO RENOMEADO');

        $this->comoGerente()->deleteJson("/api/v1/products/{$id}")->assertOk();
        $this->assertSoftDeleted('products', ['id' => $id]);
    }

    // ---------------------------------------------------------------
    // CategoryController
    // ---------------------------------------------------------------

    public function test_leitor_nao_escreve_categoria(): void
    {
        $categoria = $this->categoria();

        $this->assertNegadoPorAutorizacao(
            $this->comoLeitor()->postJson('/api/v1/categories', [
                'name' => 'Categoria Proibida',
                'slug' => 'sec01-proibida',
            ])
        );

        $this->assertNegadoPorAutorizacao(
            $this->comoLeitor()->putJson("/api/v1/categories/{$categoria->id}", [
                'name' => 'Renomeada',
                'slug' => $categoria->slug,
            ])
        );

        $this->assertNegadoPorAutorizacao(
            $this->comoLeitor()->deleteJson("/api/v1/categories/{$categoria->id}")
        );

        $this->assertDatabaseMissing('categories', ['slug' => 'sec01-proibida']);
        $this->assertSame($categoria->name, $categoria->fresh()->name);
    }

    public function test_leitor_le_categorias(): void
    {
        $categoria = $this->categoria();
        Category::factory()->withParent($categoria)->create([
            'slug' => 'sec01-filha-'.fake()->unique()->numerify('####'),
        ]);

        $this->comoLeitor()->getJson('/api/v1/categories')->assertOk();
        $this->comoLeitor()->getJson('/api/v1/categories/roots')->assertOk();
        $this->comoLeitor()->getJson("/api/v1/categories/{$categoria->id}")->assertOk();
        $this->comoLeitor()->getJson("/api/v1/categories/{$categoria->id}/children")->assertOk();
    }

    public function test_gerente_escreve_categoria(): void
    {
        $criada = $this->comoGerente()->postJson('/api/v1/categories', [
            'name' => 'Categoria Permitida',
            'slug' => 'sec01-permitida',
        ])->assertCreated();

        $id = $criada->json('data.id');

        $this->comoGerente()->putJson("/api/v1/categories/{$id}", [
            'name' => 'Categoria Editada',
            'slug' => 'sec01-permitida',
        ])->assertOk();

        $this->comoGerente()->deleteJson("/api/v1/categories/{$id}")->assertOk();
    }

    // ---------------------------------------------------------------
    // PriceController
    // ---------------------------------------------------------------

    public function test_leitor_nao_escreve_preco(): void
    {
        $produto = $this->produto();
        $preco = Price::factory()->sale()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $produto->id,
        ]);

        $this->assertNegadoPorAutorizacao(
            $this->comoLeitor()->postJson('/api/v1/prices', [
                'product_id' => (string) $produto->id,
                'amount' => '19.90',
                'currency' => 'BRL',
                'type' => 'sale',
            ])
        );

        $this->assertNegadoPorAutorizacao(
            $this->comoLeitor()->deleteJson("/api/v1/prices/{$preco->id}")
        );

        $this->assertDatabaseHas('prices', ['id' => $preco->id]);
    }

    public function test_leitor_le_precos(): void
    {
        $produto = $this->produto();
        Price::factory()->sale()->create(['tenant_id' => $this->tenant->id, 'product_id' => $produto->id]);
        Price::factory()->cost()->create(['tenant_id' => $this->tenant->id, 'product_id' => $produto->id]);

        $this->comoLeitor()->getJson("/api/v1/prices/product/{$produto->id}")->assertOk();
        $this->comoLeitor()->getJson("/api/v1/prices/history/{$produto->id}")->assertOk();
        $this->comoLeitor()->getJson("/api/v1/prices/sale/{$produto->id}")->assertOk();
        $this->comoLeitor()->getJson("/api/v1/prices/cost/{$produto->id}")->assertOk();
    }

    public function test_gerente_escreve_preco(): void
    {
        $produto = $this->produto();

        $criado = $this->comoGerente()->postJson('/api/v1/prices', [
            'product_id' => (string) $produto->id,
            'amount' => '29.90',
            'currency' => 'BRL',
            'type' => 'sale',
        ])->assertCreated();

        $this->comoGerente()
            ->deleteJson('/api/v1/prices/'.$criado->json('data.id'))
            ->assertOk();
    }

    // ---------------------------------------------------------------
    // SEC-04 — o coringa create-role não volta
    // ---------------------------------------------------------------

    public function test_create_role_nao_autoriza_escrita_em_products(): void
    {
        $produto = $this->produto();
        $categoria = $this->categoria();

        $this->assertNegadoPorAutorizacao(
            $this->comoCreateRole()->postJson('/api/v1/products', [
                'company_id' => $this->company->id,
                'sku' => 'SEC01-CORINGA',
                'name' => 'Coringa',
                'status' => 'active',
            ])
        );

        $this->assertNegadoPorAutorizacao(
            $this->comoCreateRole()->deleteJson("/api/v1/products/{$produto->id}")
        );

        $this->assertNegadoPorAutorizacao(
            $this->comoCreateRole()->deleteJson("/api/v1/categories/{$categoria->id}")
        );

        $this->assertDatabaseMissing('products', ['sku' => 'SEC01-CORINGA']);
    }

    public function test_create_role_nao_autoriza_leitura_em_products(): void
    {
        // Sem view-products a leitura também é negada: o 403 vem da Policy,
        // não de uma coleção vazia.
        $this->assertNegadoPorAutorizacao(
            $this->comoCreateRole()->getJson('/api/v1/products')
        );

        $this->assertNegadoPorAutorizacao(
            $this->comoCreateRole()->getJson('/api/v1/categories')
        );
    }

    // ---------------------------------------------------------------
    // Isolamento entre estabelecimentos permanece
    // ---------------------------------------------------------------

    public function test_autorizacao_nao_abre_produto_de_outro_estabelecimento(): void
    {
        $alheio = Product::factory()->create([
            'tenant_id' => $this->outroTenant->id,
            'company_id' => $this->outraCompany->id,
            'sku' => 'SEC01-ALHEIO',
        ]);

        // Gerente do tenant A, mesmo autorizado no seu próprio
        // estabelecimento, não alcança a entidade do tenant B.
        $this->comoGerente()->getJson("/api/v1/products/{$alheio->id}")->assertNotFound();
        $this->comoGerente()->putJson("/api/v1/products/{$alheio->id}", ['name' => 'X'])->assertNotFound();
        $this->comoGerente()->deleteJson("/api/v1/products/{$alheio->id}")->assertNotFound();

        $this->assertSame('SEC01-ALHEIO', $alheio->fresh()->sku);
    }

    public function test_tenant_de_outro_estabelecimento_no_header_continua_barrado(): void
    {
        $this->withToken($this->tokenGerente)
            ->withHeader('X-Tenant-ID', $this->outroTenant->id)
            ->getJson('/api/v1/products')
            ->assertForbidden();
    }
}
