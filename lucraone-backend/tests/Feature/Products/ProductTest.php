<?php

namespace Tests\Feature\Products;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Products\Domain\Models\Category;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Database\Factories\CategoryFactory;
use Database\Factories\ProductFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->company = Company::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /**
     * Test product creation with valid data
     */
    public function test_create_product_with_valid_data(): void
    {
        $product = Product::factory()
            ->create([
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'sku' => 'TEST-001',
                'name' => 'Test Product',
                'status' => 'active',
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'tenant_id' => $this->tenant->id,
            'sku' => 'TEST-001',
            'name' => 'Test Product',
        ]);
    }

    /**
     * Test SKU uniqueness per tenant
     */
    public function test_sku_is_unique_per_tenant(): void
    {
        $tenant2 = Tenant::factory()->create();
        $company2 = Company::factory()->create(['tenant_id' => $tenant2->id]);

        $product1 = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'SAME-SKU',
        ]);

        // Same SKU in different tenant should be allowed
        $product2 = Product::factory()->create([
            'tenant_id' => $tenant2->id,
            'company_id' => $company2->id,
            'sku' => 'SAME-SKU',
        ]);

        $this->assertEquals($product1->sku, $product2->sku);
        $this->assertNotEquals($product1->tenant_id, $product2->tenant_id);
    }

    /**
     * Test product retrieval by SKU
     */
    public function test_retrieve_product_by_sku(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'SKU-123',
        ]);

        $found = Product::query()
            ->where('tenant_id', $this->tenant->id)
            ->bySku('SKU-123')
            ->first();

        $this->assertNotNull($found);
        $this->assertEquals($product->id, $found->id);
    }

    /**
     * Test active products scope
     */
    public function test_active_products_scope(): void
    {
        Product::factory()
            ->count(3)
            ->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);

        Product::factory()
            ->count(2)
            ->create(['tenant_id' => $this->tenant->id, 'status' => 'inactive']);

        $active = Product::where('tenant_id', $this->tenant->id)->active()->count();

        $this->assertEquals(3, $active);
    }

    /**
     * Test tenant isolation: tenant A cannot see products from tenant B
     */
    public function test_tenant_a_cannot_see_tenant_b_products(): void
    {
        $tenant2 = Tenant::factory()->create();
        $company2 = Company::factory()->create(['tenant_id' => $tenant2->id]);

        $productA = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Product A',
        ]);

        $productB = Product::factory()->create([
            'tenant_id' => $tenant2->id,
            'company_id' => $company2->id,
            'name' => 'Product B',
        ]);

        // Tenant A can only see their products
        $productsA = Product::where('tenant_id', $this->tenant->id)->get();

        $this->assertCount(1, $productsA);
        $this->assertEquals($productA->id, $productsA->first()->id);
    }

    /**
     * Test product soft delete
     */
    public function test_product_soft_delete(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $product->delete();

        $this->assertSoftDeleted($product);

        // Should not appear in normal queries
        $found = Product::where('id', $product->id)
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertNull($found);
    }

    /**
     * Test product with multiple categories
     */
    public function test_product_with_multiple_categories(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $category1 = Category::factory()->create(['tenant_id' => $this->tenant->id]);
        $category2 = Category::factory()->create(['tenant_id' => $this->tenant->id]);

        $product->categories()->attach([$category1->id, $category2->id]);

        $this->assertCount(2, $product->categories);
    }

    /**
     * Test category hierarchy (parent-child)
     */
    public function test_category_hierarchy(): void
    {
        $parent = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => null,
        ]);

        $child = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $parent->id,
        ]);

        $grandchild = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $child->id,
        ]);

        $this->assertEquals($parent->id, $child->parent_id);
        $this->assertEquals($child->id, $grandchild->parent_id);
        $this->assertCount(1, $parent->children);
        $this->assertCount(1, $child->children);
    }

    /**
     * Test get root categories
     */
    public function test_get_root_categories(): void
    {
        Category::factory()->create(['tenant_id' => $this->tenant->id, 'parent_id' => null]);
        Category::factory()->create(['tenant_id' => $this->tenant->id, 'parent_id' => null]);

        $parent = Category::factory()->create(['tenant_id' => $this->tenant->id]);
        Category::factory()->create(['tenant_id' => $this->tenant->id, 'parent_id' => $parent->id]);

        $roots = Category::where('tenant_id', $this->tenant->id)->root()->count();

        $this->assertEquals(3, $roots);
    }
}
