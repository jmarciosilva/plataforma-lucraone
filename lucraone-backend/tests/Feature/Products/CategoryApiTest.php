<?php

namespace Tests\Feature\Products;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Category;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Database\Factories\CategoryFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    /**
     * Test GET /api/v1/categories
     */
    public function test_list_categories(): void
    {
        Category::factory()
            ->count(3)
            ->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug']
                ]
            ]);
    }

    /**
     * Test POST /api/v1/categories
     */
    public function test_create_category(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/categories', [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'Electronic products',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Electronics');

        $this->assertDatabaseHas('categories', [
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);
    }

    /**
     * Test GET /api/v1/categories/{id}
     */
    public function test_show_category(): void
    {
        $category = Category::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $category->id);
    }

    /**
     * Test PUT /api/v1/categories/{id}
     */
    public function test_update_category(): void
    {
        $category = Category::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v1/categories/{$category->id}", [
                'name' => 'Updated Category',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Category');
    }

    /**
     * Test DELETE /api/v1/categories/{id}
     */
    public function test_delete_category(): void
    {
        $category = Category::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200);
    }

    /**
     * Test GET /api/v1/categories/roots
     */
    public function test_get_root_categories(): void
    {
        $root1 = Category::factory()->create(['tenant_id' => $this->tenant->id]);
        $root2 = Category::factory()->create(['tenant_id' => $this->tenant->id]);

        $parent = Category::factory()->create(['tenant_id' => $this->tenant->id]);
        Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $parent->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/categories/roots');

        $response->assertStatus(200);
        // Should have at least 3 root categories
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    /**
     * Test GET /api/v1/categories/{id}/children
     */
    public function test_get_category_children(): void
    {
        $parent = Category::factory()->create(['tenant_id' => $this->tenant->id]);

        $child1 = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $parent->id,
        ]);

        $child2 = Category::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $parent->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/categories/{$parent->id}/children");

        $response->assertStatus(200);
        $children = collect($response->json('data'))->pluck('id');
        $this->assertTrue($children->contains($child1->id));
        $this->assertTrue($children->contains($child2->id));
    }

    /**
     * Test category hierarchy
     */
    public function test_create_subcategory(): void
    {
        $parent = Category::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/categories', [
                'name' => 'Subcategory',
                'slug' => 'subcategory',
                'parent_id' => $parent->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.parent_id', $parent->id);
    }

    /**
     * Test requires authentication
     */
    public function test_category_endpoints_require_auth(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(401);
    }
}
