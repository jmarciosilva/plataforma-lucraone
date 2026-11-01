<?php

namespace Database\Factories;

use App\Modules\Menu\Domain\Models\MenuSection;
use App\Modules\Menu\Domain\Models\MenuSectionItem;
use App\Modules\Products\Domain\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MenuSectionItemFactory extends Factory
{
    protected $model = MenuSectionItem::class;

    public function definition(): array
    {
        $section = MenuSection::factory()->create();

        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => $section->tenant_id,
            'menu_section_id' => $section->id,
            'product_id' => Product::factory(),
            'description' => null,
            'price_override' => null,
            'price_currency' => null,
            'position' => $this->faker->numberBetween(0, 20),
            'available' => true,
        ];
    }

    public function forSection(MenuSection $section): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $section->tenant_id,
            'menu_section_id' => $section->id,
        ]);
    }

    public function withPriceOverride(string $amount, string $currency = 'BRL'): static
    {
        return $this->state(fn (array $attributes) => [
            'price_override' => $amount,
            'price_currency' => $currency,
        ]);
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'available' => false,
        ]);
    }
}
