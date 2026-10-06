<?php

namespace Database\Factories;

use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Models\ProductPackage;
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
            'sale_presentation_type' => 'base',
            'product_package_id' => null,
            'package_name' => null,
            'package_factor' => null,
            'presentation_barcode' => fn (array $attributes) => Product::withoutGlobalScopes()->findOrFail($attributes['product_id'])->barcode,
            'sku' => strtoupper($this->faker->bothify('???-###')),
            'name' => $this->faker->words(2, true),
            'quantity' => $quantidade,
            'unit_price' => $preco,
            'total' => round($quantidade * $preco, 2),
        ];
    }

    public function legacy(): static
    {
        return $this->legacyPresentation()->state(fn (array $attributes) => ['unit' => null]);
    }

    public function legacyPresentation(): static
    {
        return $this->state(fn (array $attributes) => [
            'sale_presentation_type' => null, 'product_package_id' => null,
            'package_name' => null, 'package_factor' => null, 'presentation_barcode' => null,
        ]);
    }

    public function package(ProductPackage $package): static
    {
        $product = Product::withoutGlobalScopes()->findOrFail($package->product_id);
        if ($product->unit !== 'UN' || $package->factor < 2 || $product->tenant_id !== $package->tenant_id) {
            throw new \InvalidArgumentException('Esta embalagem não pode ser utilizada para este produto.');
        }

        return $this->state(fn (array $attributes) => [
            'tenant_id' => $package->tenant_id, 'product_id' => $package->product_id,
            'sku' => $product->sku, 'name' => $product->name, 'unit' => 'UN',
            'sale_presentation_type' => 'package', 'product_package_id' => $package->id,
            'package_name' => $package->name, 'package_factor' => $package->factor,
            'presentation_barcode' => $package->barcode, 'quantity' => $package->factor,
            'total' => fn (array $attributes) => round($package->factor * $attributes['unit_price'], 2),
        ]);
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
