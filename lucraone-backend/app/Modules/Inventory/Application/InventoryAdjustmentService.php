<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryAdjustmentService
{
    public function __construct(
        private TenantContext $context
    ) {}

    public function adjust(Product $product, string $companyId, string $type, float $quantity, ?string $reason, ?string $userId = null): Inventory
    {
        return DB::transaction(function () use ($product, $companyId, $type, $quantity, $reason, $userId) {
            $inventory = Inventory::query()
                ->where('tenant_id', $this->context->id())
                ->where('product_id', $product->id)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                $inventory = Inventory::create([
                    'tenant_id' => $this->context->id(),
                    'product_id' => $product->id,
                    'company_id' => $companyId,
                    'quantity_on_hand' => 0,
                    'reserved' => 0,
                ]);
            }

            $before = (float) $inventory->quantity_on_hand;
            $reservedBefore = (float) $inventory->reserved;
            $after = $this->quantityAfter($type, $before, $quantity);
            $reservedAfter = $this->reservedAfter($type, $reservedBefore, $quantity, $after);

            $inventory->forceFill([
                'quantity_on_hand' => $after,
                'reserved' => $reservedAfter,
            ])->save();

            InventoryMovement::create([
                'tenant_id' => $this->context->id(),
                'inventory_id' => $inventory->id,
                'product_id' => $product->id,
                'company_id' => $companyId,
                'user_id' => $userId,
                'type' => $type,
                'quantity' => $quantity,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reason' => $reason ?: 'ajuste de estoque',
                'moved_at' => now(),
            ]);

            return $inventory->fresh(['product', 'company', 'stockLevel']);
        });
    }

    private function quantityAfter(string $type, float $before, float $quantity): float
    {
        return match ($type) {
            InventoryMovement::TYPE_IN => $before + $quantity,
            InventoryMovement::TYPE_OUT => $this->ensureNotNegative($before - $quantity, 'saída maior que o saldo em mãos.'),
            InventoryMovement::TYPE_ADJUSTMENT => $quantity,
            InventoryMovement::TYPE_RESERVATION, InventoryMovement::TYPE_RELEASE => $before,
            default => throw new InvalidArgumentException('tipo de movimentação inválido.'),
        };
    }

    private function reservedAfter(string $type, float $reservedBefore, float $quantity, float $quantityOnHand): float
    {
        return match ($type) {
            InventoryMovement::TYPE_RESERVATION => $this->ensureAvailableReservation($reservedBefore + $quantity, $quantityOnHand),
            InventoryMovement::TYPE_RELEASE => $this->ensureNotNegative($reservedBefore - $quantity, 'liberação maior que a quantidade reservada.'),
            default => min($reservedBefore, $quantityOnHand),
        };
    }

    private function ensureAvailableReservation(float $reserved, float $quantityOnHand): float
    {
        if ($reserved > $quantityOnHand) {
            throw new InvalidArgumentException('reserva maior que o saldo disponível.');
        }

        return $reserved;
    }

    private function ensureNotNegative(float $value, string $message): float
    {
        if ($value < 0) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }
}
