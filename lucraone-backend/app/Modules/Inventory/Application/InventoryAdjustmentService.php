<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Automation\Domain\Events\AutomationTriggered;
use App\Modules\Automation\Domain\TriggerCatalog;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Services\ProductQuantity;
use App\Modules\Tenancy\Application\TenantContext;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryAdjustmentService
{
    public function __construct(
        private TenantContext $context
    ) {}

    public function adjust(Product $product, string $companyId, string $type, mixed $quantity, ?string $reason, ?string $userId = null): Inventory
    {
        $quantity = ProductQuantity::normalize($product->unit, $quantity);

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

            $before = BigDecimal::of($inventory->quantity_on_hand);
            $reservedBefore = BigDecimal::of($inventory->reserved);
            $after = $this->quantityAfter($type, $before, BigDecimal::of($quantity));
            $reservedAfter = $this->reservedAfter($type, $reservedBefore, BigDecimal::of($quantity), $after);

            ProductQuantity::ensureFits($after);
            ProductQuantity::ensureFits($reservedAfter);

            $inventory->forceFill([
                'quantity_on_hand' => (string) $after,
                'reserved' => (string) $reservedAfter,
            ])->save();

            InventoryMovement::create([
                'tenant_id' => $this->context->id(),
                'inventory_id' => $inventory->id,
                'product_id' => $product->id,
                'company_id' => $companyId,
                'user_id' => $userId,
                'type' => $type,
                'quantity' => $quantity,
                'quantity_before' => (string) $before,
                'quantity_after' => (string) $after,
                'reason' => $reason ?: 'ajuste de estoque',
                'moved_at' => now(),
            ]);

            $atualizado = $inventory->fresh(['product', 'company', 'stockLevel']);

            $this->anunciarEstoqueBaixo($atualizado, $before, $after);

            return $atualizado;
        });
    }

    /**
     * Dispara o gatilho de estoque baixo apenas na travessia do ponto de
     * reposição.
     *
     * Anunciar a cada movimento enquanto o saldo já está baixo encheria a caixa
     * de entrada do operador e faria ele desligar a regra.
     */
    private function anunciarEstoqueBaixo(Inventory $inventory, BigDecimal $antes, BigDecimal $depois): void
    {
        $nivel = $inventory->stockLevel;

        if (! $nivel) {
            return;
        }

        $ponto = BigDecimal::of($nivel->reorder_point);

        if (! ($depois->compareTo($ponto) <= 0 && $antes->compareTo($ponto) > 0)) {
            return;
        }

        AutomationTriggered::dispatch($inventory->tenant_id, TriggerCatalog::ESTOQUE_BAIXO, [
            'product_id' => $inventory->product_id,
            'produto' => $inventory->product?->name,
            'sku' => $inventory->product?->sku,
            'quantidade' => $depois->toFloat(),
            'ponto_reposicao' => $ponto->toFloat(),
            'company_id' => $inventory->company_id,
        ]);
    }

    private function quantityAfter(string $type, BigDecimal $before, BigDecimal $quantity): BigDecimal
    {
        return match ($type) {
            InventoryMovement::TYPE_IN => $before->plus($quantity),
            InventoryMovement::TYPE_OUT => $this->ensureNotNegative($before->minus($quantity), 'saída maior que o saldo em mãos.'),
            InventoryMovement::TYPE_ADJUSTMENT => $quantity,
            InventoryMovement::TYPE_RESERVATION, InventoryMovement::TYPE_RELEASE => $before,
            default => throw new InvalidArgumentException('tipo de movimentação inválido.'),
        };
    }

    private function reservedAfter(string $type, BigDecimal $reservedBefore, BigDecimal $quantity, BigDecimal $quantityOnHand): BigDecimal
    {
        return match ($type) {
            InventoryMovement::TYPE_RESERVATION => $this->ensureAvailableReservation($reservedBefore->plus($quantity), $quantityOnHand),
            InventoryMovement::TYPE_RELEASE => $this->ensureNotNegative($reservedBefore->minus($quantity), 'liberação maior que a quantidade reservada.'),
            default => $reservedBefore->compareTo($quantityOnHand) <= 0 ? $reservedBefore : $quantityOnHand,
        };
    }

    private function ensureAvailableReservation(BigDecimal $reserved, BigDecimal $quantityOnHand): BigDecimal
    {
        if ($reserved->compareTo($quantityOnHand) > 0) {
            throw new InvalidArgumentException('reserva maior que o saldo disponível.');
        }

        return $reserved;
    }

    private function ensureNotNegative(BigDecimal $value, string $message): BigDecimal
    {
        if ($value->compareTo('0') < 0) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }
}
