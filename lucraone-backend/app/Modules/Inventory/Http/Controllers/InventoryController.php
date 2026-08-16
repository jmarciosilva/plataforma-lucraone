<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Application\InventoryAdjustmentService;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Inventory\Http\Requests\AdjustInventoryRequest;
use App\Modules\Inventory\Http\Resources\InventoryMovementResource;
use App\Modules\Inventory\Http\Resources\InventoryResource;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

class InventoryController extends Controller
{
    public function __construct(
        private TenantContext $context,
        private InventoryAdjustmentService $adjustments
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $inventory = Inventory::query()
            ->with(['product.categories', 'company', 'stockLevel'])
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->string('company_id')->toString()))
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->string('product_id')->toString()))
            ->paginate(20);

        return InventoryResource::collection($inventory);
    }

    public function adjust(AdjustInventoryRequest $request, string $productId): InventoryResource|JsonResponse
    {
        $product = Product::query()
            ->where('tenant_id', $this->context->id())
            ->findOrFail($productId);

        try {
            $inventory = $this->adjustments->adjust(
                $product,
                $request->validated('company_id'),
                $request->validated('type'),
                (float) $request->validated('quantity'),
                $request->validated('reason'),
                $request->user()?->id
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new InventoryResource($inventory);
    }

    public function movements(string $productId): AnonymousResourceCollection
    {
        Product::query()
            ->where('tenant_id', $this->context->id())
            ->findOrFail($productId);

        $movements = InventoryMovement::query()
            ->where('product_id', $productId)
            ->with(['user', 'company'])
            ->latest('moved_at')
            ->paginate(20);

        return InventoryMovementResource::collection($movements);
    }

    public function lowStock(): AnonymousResourceCollection
    {
        $inventory = Inventory::query()
            ->with(['product', 'company', 'stockLevel'])
            ->whereHas('stockLevel', function ($query) {
                $query->whereColumn('inventories.quantity_on_hand', '<=', 'stock_levels.reorder_point');
            })
            ->paginate(20);

        return InventoryResource::collection($inventory);
    }

    public function overstock(): AnonymousResourceCollection
    {
        $inventory = Inventory::query()
            ->with(['product', 'company', 'stockLevel'])
            ->whereHas('stockLevel', function ($query) {
                $query->whereNotNull('max_qty')
                    ->whereColumn('inventories.quantity_on_hand', '>', 'stock_levels.max_qty');
            })
            ->paginate(20);

        return InventoryResource::collection($inventory);
    }
}
