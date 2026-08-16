<?php

namespace Tests\Feature\Admin;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Category;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\PriceHistory;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F2.1b — Products Web UI
 */
class ProductWebManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantAtual;

    private Tenant $outroTenant;

    private User $admin;

    private Role $adminRole;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantAtual = Tenant::factory()->active()->create(['name' => 'Casa Alta']);
        $this->outroTenant = Tenant::factory()->active()->create(['name' => 'Mercado Norte']);
        $this->company = Company::factory()->forCurrentTenant($this->tenantAtual->id)->active()->create([
            'legal_name' => 'Casa Alta Comercio',
            'trade_name' => 'Casa Alta',
        ]);

        $this->admin = User::factory()->forTenant($this->tenantAtual)->create([
            'name' => 'Ana Admin',
        ]);

        $this->adminRole = Role::factory()
            ->admin()
            ->forTenant($this->tenantAtual->id)
            ->create(['id' => (string) Str::ulid()]);

        $this->tornarAdmin($this->admin, $this->tenantAtual);
    }

    public function test_listagem_de_produtos_renderiza_apenas_tenant_atual_e_help(): void
    {
        Product::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'company_id' => (string) $this->company->id,
            'name' => 'Café Especial',
            'sku' => 'CAF-001',
        ]);
        Product::factory()->create([
            'tenant_id' => $this->outroTenant->id,
            'name' => 'Produto Outro Tenant',
        ]);

        $this->actingAs($this->admin)
            ->get(route('catalog.products.index'))
            ->assertOk()
            ->assertSee('produtos')
            ->assertSee('ajuda de produtos')
            ->assertSee('Café Especial')
            ->assertDontSee('Produto Outro Tenant');
    }

    public function test_criar_produto_salva_no_tenant_atual_com_categorias(): void
    {
        $category = Category::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'name' => 'Bebidas',
            'slug' => 'bebidas',
        ]);

        $resposta = $this->actingAs($this->admin)->post(route('catalog.products.store'), [
            'company_id' => $this->company->id,
            'sku' => 'CAF-002',
            'name' => 'Café Torrado',
            'description' => 'Pacote de café torrado.',
            'status' => 'active',
            'category_ids' => [(string) $category->id],
        ]);

        $resposta->assertSessionHasNoErrors();

        $product = Product::withoutGlobalScopes()->where('sku', 'CAF-002')->firstOrFail();

        $resposta->assertRedirect(route('catalog.products.show', $product));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'tenant_id' => $this->tenantAtual->id,
            'company_id' => $this->company->id,
            'name' => 'Café Torrado',
        ]);
        $this->assertDatabaseHas('product_categories', [
            'product_id' => $product->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_sku_duplicado_no_mesmo_tenant_retorna_erro(): void
    {
        Product::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'company_id' => $this->company->id,
            'sku' => 'REP-001',
        ]);

        $this->actingAs($this->admin)
            ->from(route('catalog.products.create'))
            ->post(route('catalog.products.store'), [
                'company_id' => (string) $this->company->id,
                'sku' => 'REP-001',
                'name' => 'Produto Repetido',
                'status' => 'active',
            ])
            ->assertRedirect(route('catalog.products.create'))
            ->assertSessionHasErrors('sku');
    }

    public function test_editar_produto_atualiza_dados_e_categorias(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'company_id' => $this->company->id,
            'name' => 'Nome Antigo',
        ]);
        $category = Category::factory()->create(['tenant_id' => $this->tenantAtual->id]);

        $this->actingAs($this->admin)
            ->put(route('catalog.products.update', $product), [
                'company_id' => (string) $this->company->id,
                'sku' => $product->sku,
                'name' => 'Nome Novo',
                'description' => 'Descrição nova',
                'status' => 'inactive',
                'category_ids' => [(string) $category->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('catalog.products.show', $product));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Nome Novo',
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('product_categories', [
            'product_id' => $product->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_arquivar_e_restaurar_produto(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'company_id' => $this->company->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('catalog.products.destroy', $product))
            ->assertRedirect(route('catalog.products.index', ['trashed' => 'with']));

        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $this->actingAs($this->admin)
            ->get(route('catalog.products.show', $product->id))
            ->assertOk()
            ->assertSee('restaurar produto');

        $this->actingAs($this->admin)
            ->post(route('catalog.products.restore', $product->id))
            ->assertRedirect(route('catalog.products.show', $product));

        $this->assertNull($product->fresh()->deleted_at);
    }

    public function test_criar_editar_arquivar_e_restaurar_categoria(): void
    {
        $parent = Category::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'name' => 'Alimentos',
            'slug' => 'alimentos',
        ]);

        $resposta = $this->actingAs($this->admin)->post(route('catalog.categories.store'), [
            'name' => 'Mercearia',
            'slug' => '',
            'description' => 'Itens de mercearia.',
            'parent_id' => (string) $parent->id,
        ]);

        $resposta->assertSessionHasNoErrors();

        $category = Category::withoutGlobalScopes()->where('slug', 'mercearia')->firstOrFail();
        $resposta->assertRedirect(route('catalog.categories.index'));

        $this->actingAs($this->admin)
            ->put(route('catalog.categories.update', $category), [
                'name' => 'Mercearia Seca',
                'slug' => 'mercearia-seca',
                'description' => 'Itens secos.',
                'parent_id' => null,
            ])
            ->assertRedirect(route('catalog.categories.show', $category));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Mercearia Seca',
            'slug' => 'mercearia-seca',
            'parent_id' => null,
        ]);

        $child = Category::factory()->withParent($category->fresh())->create([
            'name' => 'Subcategoria',
            'slug' => 'subcategoria',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('catalog.categories.destroy', $category))
            ->assertRedirect(route('catalog.categories.index', ['trashed' => 'with']));

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
        $this->assertNull($child->fresh()->parent_id);

        $this->actingAs($this->admin)
            ->get(route('catalog.categories.show', $category->id))
            ->assertOk()
            ->assertSee('restaurar categoria');

        $this->actingAs($this->admin)
            ->post(route('catalog.categories.restore', $category->id))
            ->assertRedirect(route('catalog.categories.show', $category));

        $this->assertNull($category->fresh()->deleted_at);
    }

    public function test_preco_pode_ser_criado_e_atualizado_com_historico(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'company_id' => $this->company->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('catalog.products.prices.store', $product), [
                'type' => Price::TYPE_SALE,
                'currency' => 'brl',
                'amount' => '19.90',
                'reason' => null,
            ])
            ->assertRedirect(route('catalog.products.show', $product));

        $this->assertDatabaseHas('prices', [
            'tenant_id' => $this->tenantAtual->id,
            'product_id' => $product->id,
            'currency' => 'BRL',
            'type' => Price::TYPE_SALE,
            'amount' => '19.90',
        ]);

        $this->actingAs($this->admin)
            ->post(route('catalog.products.prices.store', $product), [
                'type' => Price::TYPE_SALE,
                'currency' => 'BRL',
                'amount' => '24.90',
                'reason' => 'reajuste',
            ])
            ->assertRedirect(route('catalog.products.show', $product));

        $this->assertSame(1, Price::where('product_id', $product->id)->count());
        $this->assertDatabaseHas('price_histories', [
            'tenant_id' => $this->tenantAtual->id,
            'product_id' => $product->id,
            'old_amount' => '19.90',
            'new_amount' => '24.90',
            'reason' => 'reajuste',
        ]);
        $this->assertSame(1, PriceHistory::where('product_id', $product->id)->count());
    }

    public function test_usuario_sem_permissao_recebe_403_no_catalogo(): void
    {
        $usuario = User::factory()->forTenant($this->tenantAtual)->create();

        $this->actingAs($usuario)
            ->get(route('catalog.products.index'))
            ->assertForbidden();

        $this->actingAs($usuario)
            ->get(route('catalog.categories.index'))
            ->assertForbidden();
    }

    private function tornarAdmin(User $user, Tenant $tenant): void
    {
        foreach (['manage-products', 'view-products'] as $permissionName) {
            $permission = Permission::factory()
                ->forTenant($tenant->id)
                ->create([
                    'name' => $permissionName,
                    'description' => $permissionName,
                ]);

            $this->adminRole->grantPermission($permission);
        }

        $user->assignRole($this->adminRole, $tenant->id);
    }
}
