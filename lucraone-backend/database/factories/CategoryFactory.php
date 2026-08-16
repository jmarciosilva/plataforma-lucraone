<?php

namespace Database\Factories;

use App\Modules\Products\Domain\Models\Category;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->words(2, true);

        return [
            'id' => Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'parent_id' => null,
        ];
    }

    public function withParent(Category $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $parent->tenant_id,
            'parent_id' => $parent->id,
        ]);
    }

    public function root(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => null,
        ]);
    }
}
