<?php

namespace App\Modules\Products\Http\Controllers;

use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\PriceHistory;
use App\Modules\Products\Http\Requests\StorePriceRequest;
use App\Modules\Products\Http\Resources\PriceResource;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

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
        $price = Price::updateOrCreate(
            [
                'tenant_id' => $this->tenantContext->id(),
                'product_id' => $request->validated('product_id'),
                'currency' => $request->validated('currency'),
                'type' => $request->validated('type'),
            ],
            [
                'amount' => $request->validated('amount'),
            ]
        );

        return response()->json(
            new PriceResource($price),
            201
        );
    }

    /**
     * Get price history for a product
     */
    public function history(string $productId): AnonymousResourceCollection
    {
        $history = PriceHistory::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('product_id', $productId)
            ->orderByDesc('changed_at')
            ->paginate(50);

        return $history->through(fn ($item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'currency' => $item->currency,
            'old_amount' => (float) $item->old_amount,
            'new_amount' => (float) $item->new_amount,
            'percentage_change' => $item->percentage_change,
            'reason' => $item->reason,
            'changed_by' => $item->changedBy?->name,
            'changed_at' => $item->changed_at,
        ])->collect();
    }

    /**
     * Get current sale price for a product
     */
    public function salePrice(string $productId): JsonResponse
    {
        $price = Price::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('product_id', $productId)
            ->where('type', Price::TYPE_SALE)
            ->first();

        if (!$price) {
            return response()->json(['message' => 'Sale price not found'], 404);
        }

        return response()->json(new PriceResource($price));
    }

    /**
     * Get cost price for a product
     */
    public function costPrice(string $productId): JsonResponse
    {
        $price = Price::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('product_id', $productId)
            ->where('type', Price::TYPE_COST)
            ->first();

        if (!$price) {
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

        $price->delete();

        return response()->json(['message' => 'Price deleted successfully']);
    }
}
