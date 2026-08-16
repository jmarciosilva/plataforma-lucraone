<?php

namespace Tests\Feature\Products;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Database\Factories\ProductFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Company $company;
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->company = Company::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    /**
     * Test GET /api/v1/products
     */
    public function test_list_products(): void
    {
        Product::factory()
            ->count(3)
            ->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'sku', 'name', 'status', 'company_id']
                ]
            ]);
    }

    /**
     * Test POST /api/v1/products
     */
    public function test_create_product(): void
    {
        $response = $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson('/api/v1/products', [
                'company_id' => $this->company->id,
                'sku' => 'TEST-001',
                'name' => 'Test Product',
                'description' => 'A test product',
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.sku', 'TEST-001')
            ->assertJsonPath('data.name', 'Test Product');

        $this->assertDatabaseHas('products', [
            'sku' => 'TEST-001',
            'name' => 'Test Product',
        ]);
    }

    /**
     * Test GET /api/v1/products/{id}
     */
    public function test_show_product(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.sku', $product->sku);
    }

    /**
     * Test PUT /api/v1/products/{id}
     */
    public function test_update_product(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->putJson("/api/v1/products/{$product->id}", [
                'name' => 'Updated Product',
                'status' => 'inactive',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Product')
            ->assertJsonPath('data.status', 'inactive');
    }

    /**
     * Test DELETE /api/v1/products/{id}
     */
    public function test_delete_product(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    /**
     * Test GET /api/v1/products/search/{query}
     */
    public function test_search_products(): void
    {
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'ABC123',
            'name' => 'Laptop',
        ]);

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'XYZ789',
            'name' => 'Mouse',
        ]);

        $response = $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/products/search/ABC');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.sku', 'ABC123');
    }

    /**
     * Test requires authentication
     */
    public function test_product_endpoints_require_auth(): void
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(401);
    }

    /**
     * Test tenant isolation in API
     */
    public function test_tenant_isolation_in_api(): void
    {
        $tenant2 = Tenant::factory()->create();
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        $token2 = $user2->createToken('test')->plainTextToken;

        $product1 = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $product2 = Product::factory()->create(['tenant_id' => $tenant2->id]);

        // User 1 can only see their products
        $response = $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/products');

        $response->assertStatus(200);
        // Should not see tenant2's products
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($product2->id));
    }
}
