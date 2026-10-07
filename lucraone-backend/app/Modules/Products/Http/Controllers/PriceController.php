<?php

namespace App\Modules\Products\Http\Controllers;

use App\Modules\Products\Application\RegistrarPreco;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\PriceHistory;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Http\Requests\StorePriceRequest;
use App\Modules\Products\Http\Resources\PriceResource;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class PriceController extends Controller
{
    public function __construct(
        private TenantContext $tenantContext
    ) {}

    /**
     * Get prices for a product
     */
    public function byProduct(string $productId): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);

        $prices = Price::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('product_id', $productId)
            ->get();

        return PriceResource::collection($prices);
    }

    /**
     * Create or update a price
     */
    public function store(StorePriceRequest $request): JsonResponse
    {
        $product = Product::where('tenant_id', $this->tenantContext->id())->findOrFail($request->validated('product_id'));
        Gate::authorize('update', $product);
        $price = app(RegistrarPreco::class)->salvar($product, $request->safe()->only(['currency', 'amount', 'type']), $request->user()?->id, 'alteração pela API');

        return (new PriceResource($price))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get price history for a product
     */
    public function history(string $productId): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);

        $history = PriceHistory::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('product_id', $productId)
            ->orderByDesc('changed_at')->orderByDesc('id')
            ->paginate(50);

        return JsonResource::collection($history->through(fn ($item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'price_type' => $item->price_type,
            'event_type' => $item->event_type,
            'old_reference_cost_amount' => $item->old_reference_cost_amount,
            'new_reference_cost_amount' => $item->new_reference_cost_amount,
            'old_effective_margin_percentage' => $item->old_effective_margin_percentage,
            'new_effective_margin_percentage' => $item->new_effective_margin_percentage,
            'currency' => $item->currency,
            'old_amount' => $item->old_amount === null ? null : (float) $item->old_amount,
            'new_amount' => $item->new_amount === null ? null : (float) $item->new_amount,
            'percentage_change' => $item->percentage_change === null ? null : (float) $item->percentage_change,
            'reason' => $item->reason,
            'changed_by' => $item->changedBy?->name,
            'changed_at' => $item->changed_at,
        ]));
    }

    /**
     * Get current sale price for a product
     */
    public function salePrice(string $productId): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);

        $price = Price::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('product_id', $productId)
            ->where('type', Price::TYPE_SALE)
            ->first();

        if (! $price) {
            return response()->json(['message' => 'Sale price not found'], 404);
        }

        return response()->json(new PriceResource($price));
    }

    /**
     * Get cost price for a product
     */
    public function costPrice(string $productId): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);

        $price = Price::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('product_id', $productId)
            ->where('type', Price::TYPE_COST)
            ->first();

        if (! $price) {
            return response()->json(['message' => 'Cost price not found'], 404);
        }

        return response()->json(new PriceResource($price));
    }

    /**
     * Delete a price
     */
    public function destroy(string $priceId): JsonResponse
    {
        $price = Price::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->findOrFail($priceId);

        Gate::authorize('update', $price->product()->firstOrFail());

        app(RegistrarPreco::class)->remover($price, request()->user()?->id, 'remoção pela API');

        return response()->json(['message' => 'Price deleted successfully']);
    }
}
