<?php

namespace Database\Factories;

use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PriceFactory extends Factory
{
    protected $model = Price::class;

    public function definition(): array
    {
        return [
            'id' => Str::ulid(),
            'tenant_id' => fn (array $attributes) => Product::find($attributes['product_id'])?->tenant_id,
            'product_id' => Product::factory(),
            'currency' => 'BRL',
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'type' => 'sale',
        ];
    }

    public function cost(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Price::TYPE_COST,
            'amount' => $this->faker->randomFloat(2, 5, 500),
        ]);
    }

    public function sale(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Price::TYPE_SALE,
        ]);
    }

    public function suggestedRetail(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Price::TYPE_SUGGESTED_RETAIL,
        ]);
    }

    public function inCurrency(string $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => $currency,
        ]);
    }
}
