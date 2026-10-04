<?php

namespace App\Modules\Products\Application;

use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\PriceHistory;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Services\CalculoMargem;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RegistrarPreco
{
    public function __construct(private TenantContext $context) {}

    public function salvar(Product $product, array $data, ?string $user = null, ?string $reason = null): Price
    {
        return DB::transaction(function () use ($product, $data, $user, $reason) {
            $product = $this->bloquearProduto($product->id);

            return $this->gravar($product, $data, $user, $reason, now());
        }, 3);
    }

    /** Serializa todas as operações financeiras do produto, inclusive custo/venda concorrentes. */
    private function bloquearProduto(string $id): Product
    {
        return Product::query()->where('tenant_id', $this->context->id())->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function gravar(Product $product, array $data, ?string $user, ?string $reason, $at): Price
    {
        $type = $data['type'];
        $currency = strtoupper($data['currency']);
        if (! in_array($type, [Price::TYPE_COST, Price::TYPE_SALE, Price::TYPE_SUGGESTED_RETAIL], true) || ! preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw ValidationException::withMessages(['currency' => 'Informe uma moeda com três letras, como BRL.']);
        }
        $amount = CalculoMargem::valor($data['amount']);
        $price = $this->precos($product)->where('currency', $currency)->where('type', $type)->lockForUpdate()->first();
        $old = $price ? $this->snapshot($price) : null;
        if (! $price) {
            $price = new Price(['tenant_id' => $this->context->id(), 'product_id' => $product->id, 'currency' => $currency, 'type' => $type]);
        }
        $price->amount = $amount;
        $this->contextoVenda($price, $product);
        $new = $this->snapshot($price);
        if ($old === $new) {
            return $price;
        }
        $price->save();
        $event = $old === null ? PriceHistory::EVENT_INITIAL
            : ($old['amount'] !== $new['amount'] ? PriceHistory::EVENT_AMOUNT_CHANGED : PriceHistory::EVENT_REFERENCE_COST_CHANGED);
        $this->historico($price, $old, $new, $event, $user, $reason, $at);
        if ($type === Price::TYPE_COST) {
            $this->atualizarVendas($product, $currency, $user, $reason, $at);
        }

        return $price;
    }

    private function precos(Product $product)
    {
        return Price::query()->where('tenant_id', $this->context->id())->where('product_id', $product->id);
    }

    private function contextoVenda(Price $price, Product $product): void
    {
        $cost = $price->type === Price::TYPE_SALE
            ? $this->precos($product)->where('currency', $price->currency)->cost()->first()?->amount : null;
        $price->reference_cost_amount = $cost;
        $price->effective_margin_percentage = $price->type === Price::TYPE_SALE ? CalculoMargem::percentual($cost, $price->amount) : null;
    }

    private function atualizarVendas(Product $product, string $currency, ?string $user, ?string $reason, $at): void
    {
        foreach ($this->precos($product)->where('currency', $currency)->sale()->lockForUpdate()->get() as $sale) {
            $old = $this->snapshot($sale);
            $this->contextoVenda($sale, $product);
            $new = $this->snapshot($sale);
            if ($old === $new) {
                continue;
            }
            $sale->save();
            $this->historico($sale, $old, $new, PriceHistory::EVENT_REFERENCE_COST_CHANGED, $user, $reason, $at);
        }
    }

    private function snapshot(Price $price): array
    {
        return ['amount' => $price->amount, 'reference_cost_amount' => $price->reference_cost_amount, 'effective_margin_percentage' => $price->effective_margin_percentage];
    }

    private function historico(Price $price, ?array $old, ?array $new, string $event, ?string $user, ?string $reason, $at): void
    {
        PriceHistory::create([
            'tenant_id' => $this->context->id(), 'product_id' => $price->product_id, 'price_id' => $price->id,
            'price_type' => $price->type, 'event_type' => $event, 'currency' => $price->currency,
            'old_amount' => $old['amount'] ?? null, 'new_amount' => $new['amount'] ?? null,
            'old_reference_cost_amount' => $old['reference_cost_amount'] ?? null, 'new_reference_cost_amount' => $new['reference_cost_amount'] ?? null,
            'old_effective_margin_percentage' => $old['effective_margin_percentage'] ?? null, 'new_effective_margin_percentage' => $new['effective_margin_percentage'] ?? null,
            'changed_by' => $user, 'reason' => $reason, 'changed_at' => $at,
        ]);
    }

    public function remover(Price $price, ?string $user = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($price, $user, $reason) {
            $product = $this->bloquearProduto($price->product_id);
            $price = $this->precos($product)->whereKey($price->id)->lockForUpdate()->firstOrFail();
            $at = now();
            $this->historico($price, $this->snapshot($price), null, PriceHistory::EVENT_PRICE_REMOVED, $user, $reason, $at);
            $price->delete();
            if ($price->type === Price::TYPE_COST) {
                $this->atualizarVendas($product, $price->currency, $user, $reason, $at);
            }
        }, 3);
    }

    public function automatizar(string $productId, string $type, string $operation, mixed $value, string $reason): array
    {
        return DB::transaction(function () use ($productId, $type, $operation, $value, $reason) {
            $product = $this->bloquearProduto($productId);
            // Mantém a seleção de moeda da automação existente, sem mudar seu contrato.
            $price = $this->precos($product)->where('type', $type)->orderBy('id')->lockForUpdate()->first();
            if (! $price) {
                throw new InvalidArgumentException("o produto não tem preço de {$type} cadastrado.");
            }
            $old = $price->amount;
            $new = CalculoMargem::ajustar($old, $operation, $value);
            if ($new === $old) {
                return ['product_id' => $productId, 'price_type' => $type, 'unchanged' => true];
            }
            $this->gravar($product, ['amount' => $new, 'type' => $type, 'currency' => $price->currency], null, $reason, now());

            return ['product_id' => $productId, 'price_type' => $type, 'old_amount' => $old, 'new_amount' => $new];
        }, 3);
    }
}
