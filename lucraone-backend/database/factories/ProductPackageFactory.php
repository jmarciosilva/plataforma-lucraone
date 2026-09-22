<?php

namespace Database\Factories;

use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Models\ProductPackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductPackageFactory extends Factory
{
    protected $model = ProductPackage::class;

    public function definition(): array
    {
        $factor = $this->faker->randomElement([6, 10, 12, 24]);

        return [
            'id' => Str::ulid(),
            'tenant_id' => fn (array $attributes) => Product::withoutGlobalScopes()->find($attributes['product_id'])?->tenant_id,
            'product_id' => Product::factory(),
            'name' => "Caixa {$factor}",
            'barcode' => null,
            'factor' => $factor,
        ];
    }
}
