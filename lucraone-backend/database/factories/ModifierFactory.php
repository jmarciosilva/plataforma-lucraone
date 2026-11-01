<?php

namespace Database\Factories;

use App\Modules\Menu\Domain\Models\Modifier;
use App\Modules\Menu\Domain\Models\ModifierGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ModifierFactory extends Factory
{
    protected $model = Modifier::class;

    public function definition(): array
    {
        $group = ModifierGroup::factory()->create();

        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => $group->tenant_id,
            'group_id' => $group->id,
            'name' => $this->faker->words(2, true),
            'price_delta' => $this->faker->randomFloat(2, 0, 20),
            'position' => $this->faker->numberBetween(0, 10),
            'available' => true,
        ];
    }

    public function forGroup(ModifierGroup $group): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $group->tenant_id,
            'group_id' => $group->id,
        ]);
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'available' => false,
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'price_delta' => '0.00',
        ]);
    }
}
