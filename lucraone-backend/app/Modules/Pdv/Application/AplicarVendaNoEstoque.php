<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Inventory\Application\MovimentacaoExternaDeEstoque;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Pdv\Domain\Models\PdvReceivedSale;
use App\Modules\Pdv\Domain\Models\PdvReceivedSaleItem;
use App\Modules\Products\Domain\Models\Product;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Aplica no estoque os efeitos de uma venda recebida do PDV.
 *
 * Venda confirmada dá SAÍDA; venda cancelada ESTORNA (entrada). O estoque é por
 * (tenant, empresa, produto), e tenant/empresa vêm da própria venda — que, por
 * sua vez, os herdou do Terminal autenticado.
 *
 * É BEST-EFFORT por princípio: a venda já aconteceu no caixa e já foi aceita. Um
 * item sem produto correspondente no backend, ou uma falha pontual de estoque,
 * não pode desfazer nem recusar a venda — só é registrado para acerto posterior.
 */
class AplicarVendaNoEstoque
{
    public function __construct(
        private MovimentacaoExternaDeEstoque $estoque,
    ) {}

    public function aplicar(PdvReceivedSale $venda): void
    {
        $type = match ($venda->event_type) {
            PdvReceivedSale::EVENT_CONFIRMED => InventoryMovement::TYPE_OUT,
            PdvReceivedSale::EVENT_CANCELLED => InventoryMovement::TYPE_IN,
            default => null,
        };
        if ($type === null) {
            return;
        }

        $motivo = ($type === InventoryMovement::TYPE_OUT ? 'venda PDV ' : 'cancelamento PDV ').$venda->sale_id;

        foreach ($venda->items as $item) {
            try {
                $produto = $this->resolverProduto($venda, $item);
                if ($produto === null) {
                    Log::info('Venda PDV: item sem produto correspondente; estoque não movimentado.', [
                        'sale_id' => $venda->sale_id,
                        'product_id' => $item->product_id,
                        'sku' => $item->sku,
                    ]);

                    continue;
                }

                $this->estoque->aplicar(
                    $venda->tenant_id,
                    $venda->company_id,
                    $produto,
                    $type,
                    (string) $item->quantity,
                    $motivo,
                );
            } catch (Throwable $e) {
                // Nunca propaga: a venda já está aceita. Registra para acerto.
                Log::warning('Venda PDV: falha ao movimentar estoque de um item.', [
                    'sale_id' => $venda->sale_id,
                    'product_id' => $item->product_id,
                    'exception' => $e::class,
                ]);
            }
        }
    }

    private function resolverProduto(PdvReceivedSale $venda, PdvReceivedSaleItem $item): ?Product
    {
        $base = Product::withoutGlobalScopes()->withTrashed()
            ->where('tenant_id', $venda->tenant_id)
            ->where('company_id', $venda->company_id);

        if ($item->product_id !== null && $item->product_id !== '') {
            $porId = (clone $base)->whereKey($item->product_id)->first();
            if ($porId !== null) {
                return $porId;
            }
        }

        if ($item->sku !== null && $item->sku !== '') {
            return (clone $base)->where('sku', $item->sku)->first();
        }

        return null;
    }
}
