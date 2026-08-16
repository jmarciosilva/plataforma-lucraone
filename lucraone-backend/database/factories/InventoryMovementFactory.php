<?php

namespace Database\Factories;

use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes) => Inventory::find($attributes['inventory_id'])?->tenant_id,
            'inventory_id' => Inventory::factory(),
            'product_id' => fn (array $attributes) => Inventory::find($attributes['inventory_id'])?->product_id,
            'company_id' => fn (array $attributes) => Inventory::find($attributes['inventory_id'])?->company_id,
            'user_id' => User::factory(),
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => 10,
            'quantity_before' => 0,
            'quantity_after' => 10,
            'reason' => $this->faker->sentence(),
            'moved_at' => now(),
        ];
    }
}
