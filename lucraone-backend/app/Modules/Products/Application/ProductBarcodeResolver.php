<?php

namespace App\Modules\Products\Application;

use App\Modules\Products\Domain\Exceptions\BarcodeConflictException;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Models\ProductPackage;
use App\Modules\Products\Domain\ResolvedProductBarcode;
use App\Modules\Tenancy\Infrastructure\Persistence\TenantScope;

/**
 * Leitura exata de código de barras, preparando o futuro scanner (PM-03).
 *
 * Procura o código em `products.barcode` e em `product_packages.barcode` do
 * estabelecimento informado — comparação exata, nunca por trecho — e devolve o
 * Product base com a quantidade na unidade base. Só resolve: não vende, não
 * movimenta estoque e não decide preço.
 *
 * Resolve apenas produto operacional: `active` e não arquivado, como os
 * seletores de estoque e de pedido do painel. Embalagem não tem status, então
 * vale o do produto base.
 *
 * Fora do escopo: scanner físico, código de balança / GTIN de quantidade
 * variável, etiqueta e EAN alternativo da mesma unidade (fator 1).
 */
class ProductBarcodeResolver
{
    /**
     * @throws BarcodeConflictException quando o código está num produto e numa
     *                                  embalagem do mesmo estabelecimento
     */
    public function resolve(string $tenantId, string $barcode): ?ResolvedProductBarcode
    {
        // Tenant explícito, sem depender do contexto da requisição. O soft
        // delete continua valendo: produto arquivado não é encontrado.
        $product = Product::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('barcode', $barcode)
            ->first();

        $package = ProductPackage::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('barcode', $barcode)
            ->first();

        if ($product && $package) {
            throw BarcodeConflictException::for($barcode);
        }

        if ($product) {
            return $this->operacional($product)
                ? new ResolvedProductBarcode($barcode, $product, 1, ResolvedProductBarcode::SOURCE_PRODUCT)
                : null;
        }

        if ($package) {
            $base = Product::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->find($package->product_id);

            return $base && $this->operacional($base)
                ? new ResolvedProductBarcode($barcode, $base, $package->factor, ResolvedProductBarcode::SOURCE_PACKAGE, $package)
                : null;
        }

        return null;
    }

    private function operacional(Product $product): bool
    {
        return $product->status === 'active';
    }
}
