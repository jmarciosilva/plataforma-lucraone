<?php

namespace App\Modules\Pdv\Http\Controllers;

use App\Modules\Pdv\Http\Responses\PdvErrorResponse;
use App\Modules\Products\Application\ProductBarcodeResolver;
use App\Modules\Products\Domain\Exceptions\BarcodeConflictException;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Models\ProductPackage;
use App\Modules\Products\Domain\ResolvedProductBarcode;
use App\Modules\Tenancy\Infrastructure\Persistence\TenantScope;
use App\Modules\Terminals\Application\TerminalContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Validator;

/** Read-only, company-scoped catalog contract for an authenticated PDV. */
final class CatalogController
{
    public function __construct(
        private readonly TerminalContext $terminal,
        private readonly ProductBarcodeResolver $barcodes,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $camposPermitidos = ['search', 'per_page', 'cursor'];
        if (array_diff(array_keys($request->query()), $camposPermitidos) !== []) {
            return PdvErrorResponse::make($request, PdvErrorResponse::CODE_VALIDATION, 'Filtros de catálogo inválidos.', 422);
        }

        $validator = Validator::make($request->query(), [
            'search' => ['sometimes', 'string', 'min:1', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return PdvErrorResponse::make($request, PdvErrorResponse::CODE_VALIDATION, 'Filtros de catálogo inválidos.', 422, $validator->errors()->messages());
        }

        $filtros = $validator->validated();
        $cursor = isset($filtros['cursor']) ? Cursor::fromEncoded($filtros['cursor']) : null;
        if (isset($filtros['cursor']) && $cursor === null) {
            return PdvErrorResponse::make($request, PdvErrorResponse::CODE_VALIDATION, 'Cursor de catálogo inválido.', 422);
        }

        $query = $this->products()
            ->with([
                'prices' => fn ($prices) => $prices->where('type', Price::TYPE_SALE)->orderBy('currency'),
                'packages' => fn ($packages) => $packages->orderBy('id'),
            ])
            ->orderBy('id');

        if (isset($filtros['search'])) {
            $termo = $filtros['search'];
            $query->where(fn (Builder $products) => $products
                ->where('name', 'like', "%{$termo}%")
                ->orWhere('sku', 'like', "%{$termo}%")
                ->orWhere('barcode', 'like', "%{$termo}%"));
        }

        $page = $query->cursorPaginate((int) ($filtros['per_page'] ?? 50), ['*'], 'cursor', $cursor);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Product $product) => $this->produto($product))->values(),
            'meta' => [
                'per_page' => $page->perPage(),
                'next_cursor' => $page->nextCursor()?->encode(),
                'has_more' => $page->hasMorePages(),
            ],
        ]);
    }

    public function resolveBarcode(Request $request, string $barcode): JsonResponse
    {
        $validator = Validator::make(['barcode' => $barcode], ['barcode' => ['required', 'string', 'max:14']]);
        if ($validator->fails()) {
            return PdvErrorResponse::make($request, PdvErrorResponse::CODE_VALIDATION, 'Código de barras inválido.', 422, $validator->errors()->messages());
        }

        try {
            $resolved = $this->barcodes->resolve($this->terminal->tenantId(), $barcode, $this->terminal->companyId());
        } catch (BarcodeConflictException) {
            return PdvErrorResponse::make($request, 'barcode_conflict', 'Código de barras em conflito no catálogo desta empresa.', 409);
        }

        if ($resolved === null) {
            return PdvErrorResponse::make($request, 'product_not_found', 'Código de barras não encontrado.', 404);
        }

        return response()->json(['data' => $this->resolucao($resolved)]);
    }

    /** @return Builder<Product> */
    private function products(): Builder
    {
        return Product::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $this->terminal->tenantId())
            ->where('company_id', $this->terminal->companyId())
            ->where('status', 'active');
    }

    /** @return array<string, mixed> */
    private function produto(Product $product): array
    {
        return [
            'id' => (string) $product->id,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'unit' => $product->unit,
            'status' => $product->status,
            'sale_prices' => $product->prices->map(fn (Price $price) => [
                'amount' => (string) $price->amount,
                'currency' => $price->currency,
            ])->values(),
            'packages' => $product->packages->map(fn (ProductPackage $package) => [
                'id' => (string) $package->id,
                'name' => $package->name,
                'barcode' => $package->barcode,
                'factor' => $package->factor,
            ])->values(),
            'updated_at' => $product->updated_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function resolucao(ResolvedProductBarcode $resolved): array
    {
        $product = $resolved->product;
        $prices = $product->prices()
            ->where('type', Price::TYPE_SALE)
            ->orderBy('currency')
            ->get();

        return [
            'barcode' => $resolved->barcode,
            'source' => $resolved->source,
            'quantity' => $resolved->quantity,
            'unit' => $product->unit,
            'product' => $this->produto($product->loadMissing('packages')->setRelation('prices', $prices)),
            'package' => $resolved->package === null ? null : [
                'id' => (string) $resolved->package->id,
                'name' => $resolved->package->name,
                'barcode' => $resolved->package->barcode,
                'factor' => $resolved->package->factor,
            ],
        ];
    }
}
