<?php

namespace Tests\Feature\Products;

use App\Modules\Products\Domain\Models\Category;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Database\Factories\CategoryFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
    }

    /**
     * Test create category with valid data
     */
    public function test_create_category_with_valid_data(): void
    {
        $category = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Electronics',
        ]);

        $this->assertDatabaseHas('categories', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Electronics',
        ]);
        $this->assertNotNull($category->id);
    }

    /**
     * Test category slug is unique per tenant
     */
    public function test_category_slug_unique_per_tenant(): void
    {
        $tenant2 = Tenant::factory()->create();

        $cat1 = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'slug' => 'electronics',
        ]);

        // Same slug in different tenant should be allowed
        $cat2 = Category::factory()->create([
            'tenant_id' => $tenant2->id,
            'slug' => 'electronics',
        ]);

        $this->assertEquals('electronics', $cat1->slug);
        $this->assertEquals('electronics', $cat2->slug);
        $this->assertNotEquals($cat1->tenant_id, $cat2->tenant_id);
    }

    /**
     * Test parent-child category relationship
     */
    public function test_parent_child_category_relationship(): void
    {
        $parent = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => null,
        ]);

        $child = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals($parent->id, $child->parent->id);
        $this->assertCount(1, $parent->children);
        $this->assertEquals($child->id, $parent->children->first()->id);
    }

    /**
     * Test deeply nested category hierarchy
     */
    public function test_deeply_nested_category_hierarchy(): void
    {
        $level1 = Category::factory()->create(['tenant_id' => $this->tenant->id]);
        $level2 = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $level1->id,
        ]);
        $level3 = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $level2->id,
        ]);

        $this->assertEquals($level1->id, $level2->parent_id);
        $this->assertEquals($level2->id, $level3->parent_id);
    }

    /**
     * Test root categories scope
     */
    public function test_root_categories_scope(): void
    {
        $root1 = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => null,
        ]);

        $root2 = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => null,
        ]);

        $child = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $root1->id,
        ]);

        $roots = Category::where('tenant_id', $this->tenant->id)
            ->root()
            ->get();

        $this->assertCount(2, $roots);
        $this->assertTrue($roots->pluck('id')->contains($root1->id));
        $this->assertTrue($roots->pluck('id')->contains($root2->id));
        $this->assertFalse($roots->pluck('id')->contains($child->id));
    }

    /**
     * Test category soft delete
     */
    public function test_category_soft_delete(): void
    {
        $category = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $category->delete();

        $this->assertSoftDeleted($category);
    }

    /**
     * Test tenant isolation in categories
     */
    public function test_tenant_isolation_in_categories(): void
    {
        $tenant2 = Tenant::factory()->create();

        $catA = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Category A',
        ]);

        $catB = Category::factory()->create([
            'tenant_id' => $tenant2->id,
            'name' => 'Category B',
        ]);

        $categoriesA = Category::where('tenant_id', $this->tenant->id)->get();

        $this->assertCount(1, $categoriesA);
        $this->assertEquals($catA->id, $categoriesA->first()->id);
    }

    /**
     * Test delete parent category cascades to children
     */
    public function test_delete_parent_category(): void
    {
        $parent = Category::factory()->create(['tenant_id' => $this->tenant->id]);

        $child = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $parent->id,
        ]);

        $parent->delete();

        $this->assertSoftDeleted('categories', ['id' => $parent->id]);

        // Child still exists but parent_id should be null (set null on delete)
        $foundChild = Category::withTrashed()
            ->find($child->id);

        $this->assertNull($foundChild->parent_id);
    }

    /**
     * Test category with multiple products
     */
    public function test_category_with_multiple_products(): void
    {
        $category = Category::factory()->create(['tenant_id' => $this->tenant->id]);

        $product1 = \App\Modules\Products\Domain\Models\Product::factory()
            ->create(['tenant_id' => $this->tenant->id]);
        $product2 = \App\Modules\Products\Domain\Models\Product::factory()
            ->create(['tenant_id' => $this->tenant->id]);

        $category->products()->attach([$product1->id, $product2->id]);

        $this->assertCount(2, $category->products);
    }
}
