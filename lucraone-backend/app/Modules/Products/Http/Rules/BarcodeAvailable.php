<?php

namespace App\Modules\Products\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Código de barras livre no estabelecimento, somando produtos e embalagens.
 *
 * O scanner precisa de uma única resposta para cada código, e nenhum índice do
 * banco cobre as duas tabelas. O tenant vem explícito — não do global scope — e
 * produtos arquivados continuam ocupando o código, como no índice único.
 */
class BarcodeAvailable implements ValidationRule
{
    public function __construct(
        private ?string $tenantId,
        private ?string $ignoreProductId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $emProduto = DB::table('products')
            ->where('tenant_id', $this->tenantId)
            ->where('barcode', $value)
            ->when($this->ignoreProductId, fn ($query) => $query->where('id', '!=', $this->ignoreProductId))
            ->exists();

        $emEmbalagem = DB::table('product_packages')
            ->where('tenant_id', $this->tenantId)
            ->where('barcode', $value)
            ->exists();

        if ($emProduto || $emEmbalagem) {
            $fail('este código de barras já está em uso em um produto ou embalagem deste estabelecimento.');
        }
    }
}
