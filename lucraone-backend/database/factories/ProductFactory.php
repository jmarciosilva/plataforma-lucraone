<?php

namespace Database\Factories;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'id' => Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'company_id' => Company::factory(),
            'sku' => strtoupper(Str::random(8)),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function discontinued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'discontinued',
        ]);
    }

    public function withSku(string $sku): static
    {
        return $this->state(fn (array $attributes) => [
            'sku' => $sku,
        ]);
    }
}
