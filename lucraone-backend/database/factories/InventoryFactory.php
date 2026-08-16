<?php

namespace Database\Factories;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'product_id' => Product::factory(),
            'company_id' => Company::factory(),
            'quantity_on_hand' => $this->faker->randomFloat(3, 0, 500),
            'reserved' => 0,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'company_id' => $product->company_id,
        ]);
    }
}
