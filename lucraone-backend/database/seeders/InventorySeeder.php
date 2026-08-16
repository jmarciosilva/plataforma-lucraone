<?php

namespace Database\Seeders;

use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Inventory\Domain\Models\StockLevel;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (Tenant::withoutGlobalScopes()->get() as $tenant) {
            Product::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->orderBy('sku')
                ->get()
                ->each(function (Product $product, int $index) use ($tenant) {
                    $quantity = $this->quantityFor($index);
                    $reserved = $quantity > 10 ? 2 : 0;

                    $inventory = Inventory::withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'product_id' => $product->id,
                            'company_id' => $product->company_id,
                        ],
                        [
                            'quantity_on_hand' => $quantity,
                            'reserved' => $reserved,
                        ]
                    );

                    StockLevel::withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'product_id' => $product->id,
                            'company_id' => $product->company_id,
                        ],
                        [
                            'min_qty' => 5,
                            'reorder_point' => 10,
                            'max_qty' => 80,
                        ]
                    );

                    InventoryMovement::withoutGlobalScopes()->firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'inventory_id' => $inventory->id,
                            'type' => InventoryMovement::TYPE_ADJUSTMENT,
                            'reason' => 'saldo inicial do seeder',
                        ],
                        [
                            'product_id' => $product->id,
                            'company_id' => $product->company_id,
                            'user_id' => null,
                            'quantity' => $quantity,
                            'quantity_before' => 0,
                            'quantity_after' => $quantity,
                            'moved_at' => now(),
                        ]
                    );
                });
        }
    }

    private function quantityFor(int $index): float
    {
        return match ($index % 6) {
            0 => 3.000,
            1 => 8.000,
            2 => 18.000,
            3 => 35.000,
            4 => 82.000,
            default => 120.000,
        };
    }
}
