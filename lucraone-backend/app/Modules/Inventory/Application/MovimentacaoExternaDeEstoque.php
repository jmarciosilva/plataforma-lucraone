<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Automation\Domain\Events\AutomationTriggered;
use App\Modules\Automation\Domain\TriggerCatalog;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Services\ProductQuantity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Movimenta estoque a partir de uma origem de MÁQUINA (ex.: venda do PDV), sem
 * depender do TenantContext humano — o tenant e a empresa vêm explicitamente do
 * evento que já aconteceu, não do contexto da requisição.
 *
 * Diferença essencial para o ajuste humano (InventoryAdjustmentService): a SAÍDA
 * aqui PODE deixar o saldo negativo. A venda no caixa é fato consumado; recusar
 * a baixa não desfaz a venda, só esconde a divergência. Saldo negativo é o sinal
 * honesto de que aquele item precisa de acerto de estoque — é assim que um ERP
 * de varejo trata venda com estoque dessincronizado.
 *
 * A linha de estoque é criada sob demanda: todo item vendido passa a refletir no
 * estoque, mesmo que ainda não houvesse saldo cadastrado para ele.
 */
class MovimentacaoExternaDeEstoque
{
    /**
     * @param  string  $type  InventoryMovement::TYPE_OUT (venda) ou TYPE_IN (estorno)
     * @param  string  $quantity  quantidade positiva, na unidade base do produto
     */
    public function aplicar(string $tenantId, string $companyId, Product $product, string $type, string $quantity, string $reason, ?string $userId = null): Inventory
    {
        $quantidade = BigDecimal::of($quantity)->toScale(ProductQuantity::SCALE, RoundingMode::HalfUp);
        ProductQuantity::ensureFits($quantidade->abs());

        return DB::transaction(function () use ($tenantId, $companyId, $product, $type, $quantidade, $reason, $userId) {
            $inventory = Inventory::query()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $product->id)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                $inventory = Inventory::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $product->id,
                    'company_id' => $companyId,
                    'quantity_on_hand' => 0,
                    'reserved' => 0,
                ]);
            }

            $before = BigDecimal::of($inventory->quantity_on_hand);
            $after = $type === InventoryMovement::TYPE_OUT
                ? $before->minus($quantidade)
                : $before->plus($quantidade);

            // Sem ensureNotNegative de propósito: saldo negativo é permitido aqui.
            ProductQuantity::ensureFits($after->abs());

            $inventory->forceFill(['quantity_on_hand' => (string) $after])->save();

            InventoryMovement::create([
                'tenant_id' => $tenantId,
                'inventory_id' => $inventory->id,
                'product_id' => $product->id,
                'company_id' => $companyId,
                'user_id' => $userId,
                'type' => $type,
                'quantity' => (string) $quantidade,
                'quantity_before' => (string) $before,
                'quantity_after' => (string) $after,
                'reason' => $reason,
                'moved_at' => now(),
            ]);

            $atualizado = $inventory->fresh(['product', 'stockLevel']);

            if ($type === InventoryMovement::TYPE_OUT) {
                $this->anunciarEstoqueBaixo($atualizado, $before, $after);
            }

            return $atualizado;
        });
    }

    /**
     * Dispara o gatilho de estoque baixo só na travessia do ponto de reposição,
     * igual ao fluxo humano — evita inundar o operador a cada baixa.
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
}
