<?php

namespace Tests\Feature\Products;

use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Database\Factories\PriceFactory;
use Database\Factories\ProductFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /**
     * Test create price with valid data
     */
    public function test_create_price_with_valid_data(): void
    {
        $price = Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'amount' => 99.90,
            'type' => Price::TYPE_SALE,
        ]);

        $this->assertDatabaseHas('prices', [
            'id' => $price->id,
            'product_id' => $this->product->id,
            'amount' => 99.90,
            'type' => Price::TYPE_SALE,
        ]);
    }

    /**
     * Test one price per product, currency, type per tenant
     */
    public function test_unique_price_per_product_currency_type(): void
    {
        $price1 = Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'currency' => 'BRL',
            'type' => Price::TYPE_SALE,
            'amount' => 100.00,
        ]);

        // Attempting to create duplicate should fail
        $this->expectException(\Illuminate\Database\QueryException::class);

        Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'currency' => 'BRL',
            'type' => Price::TYPE_SALE,
            'amount' => 150.00,
        ]);
    }

    /**
     * Test sale prices scope
     */
    public function test_sale_prices_scope(): void
    {
        Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => Price::TYPE_SALE,
        ]);

        Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => Price::TYPE_COST,
        ]);

        $salePrices = Price::where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->product->id)
            ->sale()
            ->count();

        $this->assertEquals(1, $salePrices);
    }

    /**
     * Test cost prices scope
     */
    public function test_cost_prices_scope(): void
    {
        Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => Price::TYPE_COST,
            'amount' => 50.00,
        ]);

        Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => Price::TYPE_SALE,
            'amount' => 100.00,
        ]);

        $costPrice = Price::where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->product->id)
            ->cost()
            ->first();

        $this->assertNotNull($costPrice);
        $this->assertEquals(50.00, $costPrice->amount);
    }

    /**
     * Test margin percentage calculation
     */
    public function test_margin_percentage_calculation(): void
    {
        $costPrice = Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => Price::TYPE_COST,
            'amount' => 50.00,
        ]);

        $salePrice = Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => Price::TYPE_SALE,
            'amount' => 100.00,
        ]);

        // Margin = (100 - 50) / 50 * 100 = 100%
        $margin = $salePrice->margin_percentage;

        $this->assertEquals(100.0, $margin);
    }

    /**
     * Test tenant isolation in prices
     */
    public function test_tenant_isolation_in_prices(): void
    {
        $tenant2 = Tenant::factory()->create();
        $product2 = Product::factory()->create(['tenant_id' => $tenant2->id]);

        $priceA = Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'amount' => 100.00,
        ]);

        $priceB = Price::factory()->create([
            'tenant_id' => $tenant2->id,
            'product_id' => $product2->id,
            'amount' => 200.00,
        ]);

        $pricesA = Price::where('tenant_id', $this->tenant->id)->get();

        $this->assertCount(1, $pricesA);
        $this->assertEquals($priceA->id, $pricesA->first()->id);
    }

    /**
     * Test multiple currencies per product
     */
    public function test_multiple_currencies_per_product(): void
    {
        Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'currency' => 'BRL',
            'amount' => 100.00,
        ]);

        Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'currency' => 'USD',
            'type' => Price::TYPE_SALE,
            'amount' => 20.00,
        ]);

        $prices = Price::where('product_id', $this->product->id)->get();

        $this->assertCount(2, $prices);
        $currencies = $prices->pluck('currency')->unique();
        $this->assertCount(2, $currencies);
    }
}
