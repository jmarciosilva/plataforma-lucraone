<?php

namespace Database\Factories;

use App\Modules\Products\Domain\Models\Product;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Sales\Domain\Models\OrderItem;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantidade = $this->faker->randomFloat(3, 1, 10);
        $preco = $this->faker->randomFloat(2, 1, 200);

        return [
            'tenant_id' => Tenant::factory(),
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'unit' => fn (array $attributes) => Product::withoutGlobalScopes()->findOrFail($attributes['product_id'])->unit,
            'sku' => strtoupper($this->faker->bothify('???-###')),
            'name' => $this->faker->words(2, true),
            'quantity' => $quantidade,
            'unit_price' => $preco,
            'total' => round($quantidade * $preco, 2),
        ];
    }

    public function legacy(): static
    {
        return $this->state(fn (array $attributes) => ['unit' => null]);
    }

    public function forOrder(Order $order, Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'unit' => $product->unit,
            'sku' => $product->sku,
            'name' => $product->name,
        ]);
    }
}
