<?php

namespace Database\Factories;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Menu\Domain\ModifierGroupType;
use App\Modules\Menu\Domain\Models\ModifierGroup;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ModifierGroupFactory extends Factory
{
    protected $model = ModifierGroup::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'branch_id' => null,
            'name' => $this->faker->words(2, true),
            'type' => ModifierGroupType::ADDITIONAL,
            'required' => false,
            'min_selections' => 0,
            'max_selections' => null,
            'position' => $this->faker->numberBetween(0, 10),
        ];
    }

    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'branch_id' => null,
        ]);
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
        ]);
    }

    public function choice(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ModifierGroupType::CHOICE,
            'required' => true,
            'min_selections' => 1,
            'max_selections' => 1,
        ]);
    }

    public function removal(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ModifierGroupType::REMOVAL,
            'required' => false,
        ]);
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'required' => true,
            'min_selections' => max(1, $attributes['min_selections'] ?? 0),
        ]);
    }
}
