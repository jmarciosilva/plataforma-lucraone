<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Domain\Models\StockLevel;
use App\Modules\Inventory\Http\Requests\StoreStockLevelRequest;
use App\Modules\Inventory\Http\Resources\StockLevelResource;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Routing\Controller;

class StockLevelController extends Controller
{
    public function __construct(
        private TenantContext $context
    ) {}

    public function store(StoreStockLevelRequest $request, string $productId): StockLevelResource
    {
        $product = Product::query()
            ->where('tenant_id', $this->context->id())
            ->findOrFail($productId);

        $stockLevel = StockLevel::query()->updateOrCreate(
            [
                'tenant_id' => $this->context->id(),
                'product_id' => $product->id,
                'company_id' => $request->validated('company_id'),
            ],
            [
                'min_qty' => $request->validated('min_qty'),
                'max_qty' => $request->validated('max_qty'),
                'reorder_point' => $request->validated('reorder_point'),
            ]
        );

        return new StockLevelResource($stockLevel->load(['product', 'company']));
    }
}
