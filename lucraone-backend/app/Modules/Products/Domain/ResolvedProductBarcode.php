<?php

namespace App\Modules\Products\Domain;

use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Models\ProductPackage;

/**
 * Resultado da leitura exata de um código de barras: o Product base e a
 * quantidade, já na unidade base dele, que aquele código representa.
 *
 * Código do produto → quantidade 1; código de embalagem → `factor` da
 * embalagem. Produto KG com código próprio também vale 1 (1 KG): código de
 * balança com peso embutido não é interpretado aqui.
 */
final class ResolvedProductBarcode
{
    public const SOURCE_PRODUCT = 'product';

    public const SOURCE_PACKAGE = 'package';

    public function __construct(
        public readonly string $barcode,
        public readonly Product $product,
        public readonly int $quantity,
        public readonly string $source,
        public readonly ?ProductPackage $package = null,
    ) {}
}
